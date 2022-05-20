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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
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

    public function buildVariantIterator(ProductEntity $product, int $pageSize): RepositoryIterator
    {
        $criteria = new Criteria();
        $productRepository = $this->container->get('product.repository');

        $criteria->setLimit($pageSize);
        $this->addParentIdFilterWithVisibility($product, $criteria);
        $this->handleAvailableStock($criteria);
        $this->addPriceZeroFilter($criteria);
        $this->addProductAssociations($criteria);

        $context = $this->salesChannelContext->getContext();
        $context->setConsiderInheritance(false);

        return new RepositoryIterator($productRepository, $this->salesChannelContext->getContext(), $criteria);
    }

    protected function addParentIdFilterWithVisibility(ProductEntity $productEntity, Criteria $criteria): void
    {
        if (!$productEntity->getParentId()) {
            $this->adaptProductIdCriteriaWithParentId($productEntity, $criteria);

            return;
        }

        $this->adaptProductIdCriteriaWithoutParentId($productEntity, $criteria);
    }

    protected function adaptProductIdCriteriaWithParentId(ProductEntity $productEntity, Criteria $criteria): void
    {
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('parentId', $productEntity->getId()),
                    $this->getVisibilityFilterForRealVariants()
                ]
            )
        );
    }

    protected function adaptProductIdCriteriaWithoutParentId(ProductEntity $productEntity, Criteria $criteria): void
    {
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new MultiFilter(
                        MultiFilter::CONNECTION_AND,
                        [
                            new EqualsFilter('parentId', $productEntity->getParentId()),
                            $this->getVisibilityFilterForRealVariants()
                        ]
                    ),
                    new EqualsFilter('id', $productEntity->getParentId())
                ]
            )
        );

        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('id', $productEntity->getId())])
        );
    }

    protected function getVisibilityFilterForRealVariants(): ProductAvailableFilter
    {
        return new ProductAvailableFilter(
            $this->salesChannelContext->getSalesChannel()->getId(),
            ProductVisibilityDefinition::VISIBILITY_SEARCH
        );
    }

    protected function adaptCriteriaBasedOnConfiguration(Criteria $criteria): void
    {
        $mainVariantConfig = $this->config->getMainVariant();

        switch ($mainVariantConfig) {
            case MainVariant::SHOPWARE_DEFAULT:
                $this->addVisibilityFilter($criteria);
                $this->addGrouping($criteria);
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
        $activeParentFilter =  new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('parentId', null),
                new EqualsFilter('active', true)
            ]
        );

        /**
         * We still need to fetch the product if it is not active, but have children products.
         */
        $inactiveParentWithChildsFilter = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('parentId', null),
                new RangeFilter('childCount', [RangeFilter::GT => 0]),
                new EqualsFilter('active', false)
            ]
        );

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    $activeParentFilter,
                    $inactiveParentWithChildsFilter
                ]
            )
        );
    }

    protected function adaptParentCriteriaByCheapestVariant(Criteria $criteria): void
    {
        $criteria->addFilter(
            new EqualsFilter('parentId', null)
        );

        $children = $criteria->getAssociation('children');
        // TODO: Fix limit setting
        //$children->setLimit(1);
        $children->addSorting(new FieldSorting('price'));

        $this->addProductAssociations($children);
        $this->handleAvailableStock($children);
        $this->addPriceZeroFilter($children);
    }

    protected function getCriteriaWithPriceZeroFilter(
        ?int $limit = null,
        ?int $offset = null,
        ?array $productIds = null
    ): Criteria {
        $criteria = $this->buildProductCriteria($limit, $offset, $productIds);
        $this->addPriceZeroFilter($criteria);

        return $criteria;
    }

    protected function buildProductCriteria(
        ?int $limit = null,
        ?int $offset = null,
        ?array $productIds = null
    ): Criteria {
        $criteria = new Criteria($productIds);
        $criteria->addSorting(new FieldSorting('createdAt'));
        $criteria->addSorting(new FieldSorting('id'));

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
        $this->addGrouping($criteria);

        /** @var IdSearchResult $result */
        $result = $this->container->get('product.repository')->searchIds(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        return $result->getTotal();
    }

    public function getMaxPropertiesCount(ProductEntity $productEntity): int
    {
        $criteria = new Criteria([$productEntity->getParentId() ?? $productEntity->getId()]);
        /** @var EntityRepository $productRepository */
        $productRepository = $this->container->get('product.repository');

        $criteria->addAggregation(
            new TermsAggregation(
                'per-children',
                'children.id',
                null,
                null,
                new TermsAggregation(
                    'property-ids',
                    'children.properties.id',
                    null,
                    null
                )
            )
        );

        /** @var TermsResult $aggregation */
        $aggregation = $productRepository
            ->aggregate($criteria, $this->salesChannelContext->getContext())
            ->get('per-children');

        $maxCount = $productEntity->getPropertyIds() ? count($productEntity->getPropertyIds()) : 0;
        foreach ($aggregation->getBuckets() as $bucket) {
            if ($bucket->getCount() > $maxCount) {
                $maxCount = $bucket->getCount();
            }
        }

        return $maxCount;
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
        $criteria = $this->getCriteriaWithPriceZeroFilter($limit, $offset);
        $this->adaptCriteriaBasedOnConfiguration($criteria);

        if ($productId) {
            $this->addProductIdFilters($criteria, $productId);
        }

        $products = $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        $mainVariantConfig = $this->config->getMainVariant();
        if ($mainVariantConfig === MainVariant::CHEAPEST) {
            return $this->getCheapestProducts($products->getEntities());
        }

        $mainVariants = $this->getConfiguredMainVariants($products->getEntities());

        if (!$mainVariants) {
            return $products;
        }

        return $mainVariants;
    }

    protected function getCheapestProducts(ProductCollection $products): EntitySearchResult
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

        return EntitySearchResult::createFrom($cheapestVariants);
    }

    protected function getConfiguredMainVariants(ProductCollection $products): ?EntitySearchResult
    {
        $realProductIds = [];

        foreach ($products as $product) {
            /**
             * If product is inactive, try to fetch it's first product.
             * This is related to main product by parent configuration.
             */
            if (!$product->getMainVariantId()) {
                $realProductIds[] = $product->getId();

                continue;
            }

            $realProductIds[] = $product->getMainVariantId();
        }

        if (empty($realProductIds)) {
            return null;
        }

        return $this->getRealMainVariants($realProductIds);
    }

    protected function getFirstChildren(ProductEntity $product): ?ProductEntity
    {
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('parentId', $product->getId()));
        $criteria->setLimit(1);
        $this->addVisibilityFilter($criteria);

        return $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        )->first();
    }

    protected function getRealMainVariants(array $productIds): EntitySearchResult
    {
        $criteria = $this->getCriteriaWithPriceZeroFilter(null, null, $productIds);

        return $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );
    }
}
