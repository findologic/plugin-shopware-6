<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

use function array_diff;

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
        $criteria = $this->getCriteriaWithProductVisibility($limit, $offset);

        if ($productId) {
            $this->addProductIdFilters($criteria, $productId);
        }

        /** @var EntitySearchResult $result */
        $result = $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        /** @var ProductCollection $visibleProductsCollection */
        $visibleProductsCollection = $result->getEntities();
        if ($visibleProductsCollection->count() !== $limit) {
            $inactiveProductIds = $this->getInactiveProductIds($limit, $offset, $productId, $visibleProductsCollection);
            $variants = $this->searchActiveVariants($inactiveProductIds, $limit, $offset, $productId);
            foreach ($variants as $variant) {
                $result->add($variant);
            }
        }

        return $result;
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
        $criteria->addSorting(new FieldSorting('product.name'));

        Utils::addProductAssociations($criteria);

        $assoc = $criteria->getAssociation('product.children');
        $assoc->addSorting(new FieldSorting('name'));
    }

    private function getCriteriaWithProductVisibility(?int $limit = null, ?int $offset = null): Criteria
    {
        $criteria = $this->buildProductCriteria($limit, $offset);

        return $criteria->addFilter(
            new ProductAvailableFilter(
                $this->salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );
    }

    private function buildProductCriteria(?int $limit = null, ?int $offset = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parent.id', null));

        $this->addProductAssociations($criteria);

        if ($offset !== null) {
            $criteria->setOffset($offset);
        }
        if ($limit !== null) {
            $criteria->setLimit($limit);
        }

        return $criteria;
    }

    private function buildActiveVariantCriteria(?int $limit = null, ?int $offset = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('parentId', null)]));

        $this->addProductAssociations($criteria);

        if ($offset !== null) {
            $criteria->setOffset($offset);
        }
        if ($limit !== null) {
            $criteria->setLimit($limit);
        }

        $criteria->addFilter(
            new ProductAvailableFilter(
                $this->salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        return $criteria;
    }

    private function addProductIdFilters(Criteria $criteria, string $productId): void
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

    private function searchActiveVariants(
        array $inactiveProductIds,
        ?int $limit,
        ?int $offset,
        ?string $productId
    ): array {
        $criteria = $this->buildActiveVariantCriteria($limit, $offset);
        $criteria->addFilter(new EqualsAnyFilter('parentId', $inactiveProductIds));

        if ($productId) {
            $this->addProductIdFilters($criteria, $productId);
        }

        $variants = $this->container->get('product.repository')
            ->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities();

        $activeVariant = [];
        foreach ($variants as $variant) {
            // We only need to get the first active variant, so if we already have the the first variant we do not
            // check the rest of the variants.
            if (!isset($activeVariant[$variant->getParentId()])) {
                $activeVariant[$variant->getParentId()] = $variant;
            }
        }

        return $activeVariant;
    }

    private function getInactiveProductIds(
        ?int $limit,
        ?int $offset,
        ?string $productId,
        ProductCollection $visibleProductsCollection
    ): array {
        $allProductsResult = $this->searchAllProducts($limit, $offset, $productId);
        $allProductCollection = $allProductsResult->getEntities();
        $allProductIds = $allProductCollection->getIds();
        $foundIds = $visibleProductsCollection->getIds();

        return array_diff($allProductIds, $foundIds);
    }
}
