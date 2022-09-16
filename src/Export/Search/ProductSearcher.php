<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\Shopware6Common\Export\Search\AbstractProductSearcher;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductSearcher extends AbstractProductSearcher
{
    protected SalesChannelContext $salesChannelContext;

    protected EntityRepository $productRepository;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        EntityRepository $productRepository,
        ProductCriteriaBuilder $productCriteriaBuilder
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->productRepository = $productRepository;

        parent::__construct($productCriteriaBuilder);
    }

    protected function fetchProducts(?int $limit = null, ?int $offset = null, ?string $productId = null): array
    {
        $criteria = $this->buildCriteria($limit, $offset, $productId);

        $productResult = $this->productRepository->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );
        /** @var ProductCollection $products */
        $products = $productResult->getEntities();

        return $products->getElements();
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

    /**
     * @param ProductEntity[] $products
     * @return ProductEntity[]
     */
    protected function getCheapestProducts(array $products): array
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

        return $cheapestVariants->getElements();
    }

    /**
     * @param ProductEntity[] $products
     * @return ?ProductEntity[]
     */
    protected function getConfiguredMainVariants(array $products): ?array
    {
        $realProductIds = [];

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

    /**
     * @param string[] $productIds
     * @return ?ProductEntity[]
     */
    protected function getRealMainVariants(array $productIds): array
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder
            ->withIds($productIds)
            ->withDefaultCriteria()
            ->withVisibilityFilter();

        return $this->productRepository->search(
            $this->productCriteriaBuilder->build(),
            $this->salesChannelContext->getContext()
        )->getEntities()->getElements();
    }

    public function buildVariantIterator(ProductEntity $product, int $pageSize): RepositoryIterator
    {
        $this->productCriteriaBuilder->reset();
        $this->productCriteriaBuilder
            ->withLimit($pageSize)
            ->withParentIdFilterWithVisibility($product->getId(), $product->getParentId())
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
