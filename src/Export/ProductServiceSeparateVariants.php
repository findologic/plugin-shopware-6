<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Findologic\MainVariant;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductServiceSeparateVariants
{
    public const CONTAINER_ID = 'fin_search.product_service_separate_variants';

    /** @var Config */
    private $config;

    /** @var ContainerInterface */
    private $container;

    /** @var SalesChannelContext|null */
    private $salesChannelContext;

    public function __construct(
        ContainerInterface $container,
        ?SalesChannelContext $salesChannelContext = null,
        ?Config $config = null
    ) {
        $this->container = $container;
        $this->salesChannelContext = $salesChannelContext;
        $this->config = $config ?? $container->get(Config::class);
    }

    public static function getInstance(
        ContainerInterface $container,
        ?SalesChannelContext $salesChannelContext,
        ?Config $config = null
    ): ProductServiceSeparateVariants {
        if ($container->has(self::CONTAINER_ID)) {
            $productService = $container->get(self::CONTAINER_ID);
        } else {
            $productService = new ProductServiceSeparateVariants($container, $salesChannelContext, $config);
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

    public function setConfig(Config $config): void
    {
        $this->config = $config;
    }

    public function getSalesChannelContext(): ?SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getConfig(): ?Config
    {
        return $this->config;
    }

    public function searchVisibleProducts(
        ?int $limit = null,
        ?int $offset = null,
        ?string $productId = null
    ): EntitySearchResult {
        $result = $this->getVisibleProducts($limit, $offset, $productId);

        return $result;
    }

    public function searchVisibleVariants(
        ProductEntity $product,
        int $pageSize,
        int $page = 0
    ): EntitySearchResult {
        $productRepository = $this->container->get('product.repository');

        $criteria = new Criteria();
        $criteria->setLimit($pageSize);
        $criteria->setOffset($page);

        $criteria->addFilter(
            new EqualsFilter('parentId', $product->getId())
        );

        $this->addVisibilityFilter($criteria);
        $this->handleAvailableStock($criteria);
        $this->addPriceZeroFilter($criteria);
        $this->addProductAssociations($criteria);

        $result = $productRepository->search($criteria, $this->salesChannelContext->getContext());

        return EntitySearchResult::createFrom($result);
    }

    protected function adaptCriteriaBasedOnConfiguration(Criteria $criteria): void
    {
        $mainVariantConfig = $this->config->getMainVariant();

        switch ($mainVariantConfig) {
            case MainVariant::SHOPWARE_DEFAULT:
                break;
            case MainVariant::MAIN_PARENT:
                $this->adaptParentCriteriaByMainProduct($criteria);
                break;
            case MainVariant::CHEAPEST:
                $this->adaptParentCriteriaByCheapestVariant($criteria);
                break;
            default:
                throw new InvalidArgumentException($mainVariantConfig);
        }
    }

    protected function adaptParentCriteriaByMainProduct(Criteria $criteria): void
    {
        $criteria->addFilter(
            new EqualsFilter('parentId', null)
        );
    }

    protected function adaptParentCriteriaByCheapestVariant(Criteria $criteria): void
    {
        $children = $criteria->getAssociation('children');
        $children->setLimit(1);
        $children->addSorting(
            new FieldSorting('price', FieldSorting::ASCENDING)
        );

        $this->handleAvailableStock($children);
        $this->addVisibilityFilter($children);
        $this->addPriceZeroFilter($children);
    }

    protected function getCriteriaWithProductVisibility(?int $limit = null, ?int $offset = null): Criteria
    {
        $criteria = $this->buildProductCriteria($limit, $offset);
        $this->addVisibilityFilter($criteria);
        $this->addPriceZeroFilter($criteria);

        return $criteria;
    }

    protected function buildProductCriteria(?int $limit = null, ?int $offset = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt'));
        $criteria->addSorting(new FieldSorting('id'));

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

    public function getAllCustomerGroups(): array
    {
        return $this->container->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();
    }

    protected function addVisibilityFilter(Criteria $criteria): void
    {
        $criteria->addFilter(
            new ProductAvailableFilter(
                $this->salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );
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

    protected function addProductAssociations(Criteria $criteria): void
    {
        Utils::addProductAssociations($criteria);
    }

    protected function addPriceZeroFilter(Criteria $criteria): void
    {
        $criteria->addFilter(
            new RangeFilter('price', [
                RangeFilter::GT => 0
            ])
        );
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
        $this->adaptCriteriaBasedOnConfiguration($criteria);

        if ($productId) {
            $this->addProductIdFilters($criteria, $productId);
        }

        $products = $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        $mainVariantConfig = $this->config->getMainVariant();
        if ($mainVariantConfig !== MainVariant::CHEAPEST) {
            return $products;
        }

        $mainProducts = $this->getCheapestProducts($products->getEntities());

        return EntitySearchResult::createFrom($mainProducts);
    }

    protected function getCheapestProducts(ProductCollection $products): ProductCollection
    {
        $cheapestVariants = new ProductCollection();

        foreach ($products as $product) {
            $cheapestVariant = $product->getChildren()->first();

            if ($cheapestVariant === null) {
                $cheapestVariants->add($product);

                continue;
            }

            $currencyId = $this->salesChannelContext->getSalesChannel()->getCurrencyId();
            $productPrice = $product->getCurrencyPrice($currencyId);
            $cheapestVariantPrice = $cheapestVariant->getCurrencyPrice($currencyId);
            $realCheapestProduct = $productPrice->getGross() < $cheapestVariantPrice->getGross()
                ? $product : $cheapestVariant;

            $cheapestVariants->add($realCheapestProduct);
        }

        return $cheapestVariants;
    }
}
