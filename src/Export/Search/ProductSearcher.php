<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Search\AbstractProductSearcher;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\Product\ProductCollection;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity;

class ProductSearcher extends AbstractProductSearcher
{
    public function __construct(
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly EntityRepository $productRepository,
        PluginConfig $pluginConfig,
        ExportContext $exportContext,
        ProductCriteriaBuilder $productCriteriaBuilder,
    ) {
        parent::__construct($pluginConfig, $exportContext, $productCriteriaBuilder);
    }

    protected function fetchProducts(
        ?int $limit = null,
        ?int $offset = null,
        ?string $productId = null
    ): ProductCollection {
        $criteria = $this->buildCriteria($limit, $offset, $productId);

        $productResult = $this->productRepository->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        /** @var ProductCollection $products */
        $products = Utils::createSdkCollection(
            ProductCollection::class,
            ProductEntity::class,
            $productResult->getEntities()
        );

        return $products;
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

    public function findTotalProductCount(): int
    {
        $criteria = $this->productCriteriaBuilder
            ->withDisplayGroupFilter()
            ->withOutOfStockFilter()
            ->build();

        $idResult = $this->productRepository->searchIds($criteria, $this->salesChannelContext->getContext());

        return $idResult->getTotal();
    }

    public function findMaxPropertiesCount(string $productId, ?string $parentId, ?array $propertyIds): int
    {
        $criteria = new Criteria([$parentId ?? $productId]);

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

        $maxCount = $propertyIds ? count($propertyIds) : 0;
        foreach ($aggregation->getBuckets() as $bucket) {
            if ($bucket->getCount() > $maxCount) {
                $maxCount = $bucket->getCount();
            }
        }

        return $maxCount;
    }

    protected function getCheapestChild(string $productId): ?ProductEntity
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder
            ->withChildCriteria($productId)
            ->withProductAssociations();

        $product = $this->productRepository->search(
            $this->productCriteriaBuilder->build(),
            $this->salesChannelContext->getContext()
        )->first();

        /** @var ?ProductEntity $product */
        $product = Utils::createSdkEntity(ProductEntity::class, $product);

        return $product;
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

    /**
     * @param string[] $productIds
     */
    protected function getRealMainVariants(array $productIds): ProductCollection
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder
            ->withIds($productIds)
            ->withDefaultCriteria()
            ->withVisibilityFilter();

        $productResult = $this->productRepository->search(
            $this->productCriteriaBuilder->build(),
            $this->salesChannelContext->getContext()
        );

        /** @var ProductCollection $products */
        $products = Utils::createSdkCollection(
            ProductCollection::class,
            ProductEntity::class,
            $productResult->getEntities()
        );

        return $products;
    }

    public function buildVariantIterator(ProductEntity $product, int $pageSize): VariantIterator
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder
            ->withIdSorting()
            ->withLimit($pageSize)
            ->withParentIdFilterWithVisibility($product->id, $product->parentId)
            ->withOutOfStockFilter()
            ->withPriceZeroFilter()
            ->withVariantAssociations($product->categoryIds ?? $product->categoryTree, $product->propertyIds);

        return new VariantIterator(
            $this->productRepository,
            $this->salesChannelContext->getContext(),
            $this->productCriteriaBuilder->build()
        );
    }
}
