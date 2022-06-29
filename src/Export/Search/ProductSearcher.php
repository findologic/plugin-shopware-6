<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\FinSearch\Findologic\MainVariant;
use FINDOLOGIC\FinSearch\Struct\Config;
use InvalidArgumentException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductSearcher
{
    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    /**
     * @var EntityRepository
     */
    protected $productRepository;

    /**
     * @var ProductCriteriaBuilder
     */
    protected $productCriteriaBuilder;

    /**
     * @var Config
     */
    protected $config;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        EntityRepository $productRepository,
        ProductCriteriaBuilder $productCriteriaBuilder,
        Config $config
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->productRepository = $productRepository;
        $this->productCriteriaBuilder = $productCriteriaBuilder;
        $this->config = $config;
    }

    public function setConfig(Config $config): void
    {
        $this->config = $config;
    }

    public function findTotalProductCount(): int
    {
        $criteria = $this->productCriteriaBuilder
            ->withDisplayGroupFilter()
            ->withOutOfStockFilter()
            ->build();

        $idResult = $this->productRepository->searchIds($criteria, $this->salesChannelContext->getContext());

        return $idResult->getTotal();
    }

    public function findMaxPropertiesCount(ProductEntity $productEntity): int
    {
        $criteria = new Criteria([$productEntity->getParentId() ?? $productEntity->getId()]);
        /** @var EntityRepository $productRepository */

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
        $aggregation = $this->productRepository
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

    public function findVisibleProducts(
        ?int $limit = null,
        ?int $offset = null,
        ?string $productId = null
    ): EntitySearchResult {
        $criteria = $this->buildCriteria($limit, $offset, $productId);

        $productResult = $this->productRepository->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );
        /** @var ProductCollection $products */
        $products = $productResult->getEntities();

        $mainVariantConfig = $this->config->getMainVariant();
        if ($mainVariantConfig === MainVariant::CHEAPEST) {
            return $this->getCheapestProducts($products);
        }

        return $this->getConfiguredMainVariants($products) ?: $productResult;
    }

    protected function buildCriteria(
        ?int $limit = null,
        ?int $offset = null,
        ?string $productId = null
    ): Criteria {
        $this->productCriteriaBuilder->withDefaultCriteria($limit, $offset, $productId);
        $this->adaptCriteriaBasedOnConfiguration();

        return $this->productCriteriaBuilder->build();
    }

    protected function adaptCriteriaBasedOnConfiguration(): void
    {
        $mainVariantConfig = $this->config->getMainVariant();

        switch ($mainVariantConfig) {
            case MainVariant::SHOPWARE_DEFAULT:
                $this->adaptParentCriteriaByShopwareDefault();
                break;
            case MainVariant::MAIN_PARENT:
            case MainVariant::CHEAPEST:
                $this->adaptParentCriteriaByMainOrCheapestProduct();
                break;
            default:
                throw new InvalidArgumentException($mainVariantConfig);
        }
    }

    protected function adaptParentCriteriaByShopwareDefault(): void
    {
        $this->productCriteriaBuilder
            ->withPriceZeroFilter()
            ->withVisibilityFilter()
            ->withDisplayGroupFilter();
    }

    protected function adaptParentCriteriaByMainOrCheapestProduct(): void
    {
        $this->productCriteriaBuilder
            ->withActiveParentOrInactiveParentWithVariantsFilter();
    }

    protected function getCheapestProducts(ProductCollection $products): EntitySearchResult
    {
        $cheapestVariants = new ProductCollection();

        foreach ($products as $product) {
            $currencyId = $this->salesChannelContext->getSalesChannel()->getCurrencyId();
            $productPrice = $product->getCurrencyPrice($currencyId);

            if (!$cheapestVariant = $this->getCheapestChild($product->getId())) {
                if ($productPrice->getGross() > 0.0 && $product->getActive()) {
                    $cheapestVariants->add($product);
                }

                continue;
            }

            $cheapestVariantPrice = $cheapestVariant->getCurrencyPrice($currencyId);

            if ($productPrice->getGross() === 0.0) {
                $realCheapestProduct = $cheapestVariant;
            } else {
                $realCheapestProduct = $productPrice->getGross() <= $cheapestVariantPrice->getGross()
                    ? $product
                    : $cheapestVariant;
            }

            $cheapestVariants->add($realCheapestProduct);
        }

        return EntitySearchResult::createFrom($cheapestVariants);
    }

    protected function getConfiguredMainVariants(ProductCollection $products): ?EntitySearchResult
    {
        $realProductIds = [];

        /** @var ProductEntity $product */
        foreach ($products as $product) {
            if ($mainVariantId = $product->getMainVariantId()) {
                $realProductIds[] = $mainVariantId;

                continue;
            }

            /**
             * If product is inactive, try to fetch first variant product.
             * This is related to main product by parent configuration.
             */
            if ($product->getActive()) {
                $realProductIds[] = $product->getId();
            } elseif ($childrenProductId = $this->getFirstVisibleChildId($product->getId())) {
                $realProductIds[] = $childrenProductId;
            }
        }

        if (empty($realProductIds)) {
            return null;
        }

        return $this->getRealMainVariants($realProductIds);
    }

    protected function getCheapestChild(string $productId): ?ProductEntity
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder
            ->withChildCriteria($productId)
            ->withProductAssociations();

        return $this->productRepository->search(
            $this->productCriteriaBuilder->build(),
            $this->salesChannelContext->getContext()
        )->first();
    }

    protected function getFirstVisibleChildId(string $productId): ?string
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder->withChildCriteria($productId);

        return $this->productRepository->searchIds(
            $this->productCriteriaBuilder->build(),
            $this->salesChannelContext->getContext()
        )->firstId();
    }

    protected function getRealMainVariants(array $productIds): EntitySearchResult
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder
            ->withIds($productIds)
            ->withDefaultCriteria()
            ->withVisibilityFilter();

        return $this->productRepository->search(
            $this->productCriteriaBuilder->build(),
            $this->salesChannelContext->getContext()
        );
    }

    public function buildVariantIterator(ProductEntity $product, int $pageSize): RepositoryIterator
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder
            ->withLimit($pageSize)
            ->withParentIdFilterWithVisibility($product)
            ->withOutOfStockFilter()
            ->withPriceZeroFilter()
            ->withVariantAssociations();

        return new RepositoryIterator(
            $this->productRepository,
            $this->salesChannelContext->getContext(),
            $this->productCriteriaBuilder->build()
        );
    }
}
