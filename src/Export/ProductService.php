<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductService
{
    public const CONTAINER_ID = 'fin_search.product_service';

    /** @var ContainerInterface */
    private $container;

    /** @var SalesChannelContext|null */
    private $salesChannelContext;

    public function __construct(ContainerInterface $container, ?SalesChannelContext $salesChannelContext = null)
    {
        $this->container = $container;
        $this->salesChannelContext = $salesChannelContext;
    }

    public static function getInstance(
        ContainerInterface $container,
        ?SalesChannelContext $salesChannelContext
    ): ProductService {
        if ($container->has(self::CONTAINER_ID)) {
            $productService = $container->get(self::CONTAINER_ID);
        } else {
            $productService = new ProductService($container, $salesChannelContext);
            $container->set(self::CONTAINER_ID, $productService);
        }

        if ($salesChannelContext && !$productService->getSalesChannelContext()) {
            $productService->setSalesChannelContext($salesChannelContext);
        }

        return $productService;
    }

    public function setSalesChannelContext(SalesChannelContext $salesChannelContext): void
    {
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getSalesChannelContext(): ?SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getTotalProductCount(): int
    {
        $criteria = $this->buildProductCriteria();

        /** @var IdSearchResult $result */
        $result = $this->container->get('product.repository')->searchIds(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        return $result->getTotal();
    }

    public function searchVisibleProducts(
        ?int $limit = null,
        ?int $offset = null,
        ?string $productId = null
    ): EntitySearchResult {
        $result = $this->getVisibleProducts($limit, $offset, $productId);
        $products = $this->buildProductsWithVariantInformation($result);

        return EntitySearchResult::createFrom($products);
    }

    public function searchAllProducts(
        ?int $limit = null,
        ?int $offset = null,
        ?string $productId = null
    ): EntitySearchResult {
        $criteria = $this->buildProductCriteria($limit, $offset);

        if ($productId) {
            $this->addProductIdFilters($criteria, $productId);
        }

        return $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );
    }

    public function getAllCustomerGroups(): array
    {
        return $this->container->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();
    }

    protected function addProductAssociations(Criteria $criteria): void
    {
        Utils::addProductAssociations($criteria);
    }

    /**
     * If the given product is a parent product, returns all children of the product.
     * In case the given product already is a child, all siblings and the parent are returned. The siblings
     * do not include the given product itself.
     */
    protected function getChildrenOrSiblings(ProductEntity $product): ?ProductCollection
    {
        if (!$product->getParentId()) {
            return $product->getChildren();
        }

        $productRepository = $this->container->get('product.repository');
        $criteria = new Criteria([$product->getParentId()]);

        // Only get children of the same display group.
        $childrenCriteria = $criteria->getAssociation('children');
        $childrenCriteria->addFilter(
            new EqualsFilter('displayGroup', $product->getDisplayGroup())
        );

        $this->addProductAssociations($criteria);

        /** @var ProductCollection $result */
        $result = $productRepository->search($criteria, $this->salesChannelContext->getContext());

        // Remove the given children, as the child product is considered as the product, which is shown
        // in the storefront. As we also want to get the data from the parent, we also manually add it here.
        $children = $result->first()->getChildren();
        $children->remove($product->getId());
        $children->add($result->first());

        return $children;
    }

    protected function getCriteriaWithProductVisibility(?int $limit = null, ?int $offset = null): Criteria
    {
        $criteria = $this->buildProductCriteria($limit, $offset);

        return $criteria->addFilter(
            new ProductAvailableFilter(
                $this->salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );
    }

    protected function buildProductCriteria(?int $limit = null, ?int $offset = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt'));

        $this->addGrouping($criteria);
        $this->handleAvailableStock($criteria);
        $this->addProductAssociations($criteria);

        if ($offset !== null) {
            $criteria->setOffset($offset);
        }
        if ($limit !== null) {
            $criteria->setLimit($limit);
        }

        return $criteria;
    }

    /**
     * @see \Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::addGrouping()
     */
    protected function addGrouping(Criteria $criteria): void
    {
        $criteria->addGroupField(new FieldGrouping('displayGroup'));

        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('displayGroup', null)]
            )
        );
    }

    /**
     * @see \Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::handleAvailableStock()
     */
    protected function handleAvailableStock(Criteria $criteria): void
    {
        $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();
        $systemConfigService = $this->container->get(SystemConfigService::class);

        $hide = $systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);
        if (!$hide) {
            return;
        }

        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('product.isCloseout', true),
                    new EqualsFilter('product.available', false),
                ]
            )
        );
    }

    protected function addProductIdFilters(Criteria $criteria, string $productId): void
    {
        $productFilter = [
            new EqualsFilter('ean', $productId),
            new EqualsFilter('manufacturerNumber', $productId),
            new EqualsFilter('productNumber', $productId),
        ];

        // Only add the id filter in case the provided value is a valid uuid, to prevent Shopware
        // from throwing an exception in case it is not.
        if (Uuid::isValid($productId)) {
            $productFilter[] = new EqualsFilter('id', $productId);
        }

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                $productFilter
            )
        );
    }

    protected function getVisibleProducts(?int $limit, ?int $offset, ?string $productId): EntitySearchResult
    {
        $criteria = $this->getCriteriaWithProductVisibility($limit, $offset);

        if ($productId) {
            $this->addProductIdFilters($criteria, $productId);
        }

        return $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );
    }

    protected function buildProductsWithVariantInformation(EntitySearchResult $result): ProductCollection
    {
        $products = new ProductCollection();

        /** @var ProductEntity $product */
        foreach ($result->getEntities() as $product) {
            if ($product->getMainVariantId() !== null) {
                if (!$product = $this->getRealMainProductWithVariants($product->getMainVariantId())) {
                    continue;
                }
            }

            $this->assignChildrenOrSiblings($product);
            $products->add($product);
        }

        return $products;
    }

    protected function getRealMainProductWithVariants(string $realMainProductId): ?ProductEntity
    {
        return $this->getVisibleProducts(1, 0, $realMainProductId)->first();
    }

    protected function assignChildrenOrSiblings(ProductEntity $product): void
    {
        $children = $this->getChildrenOrSiblings($product);
        $product->setChildren($children);
    }
}
