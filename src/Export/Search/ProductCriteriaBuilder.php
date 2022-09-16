<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\Shopware6Common\Export\Constants;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Search\AbstractProductCriteriaBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductCriteriaBuilder extends AbstractProductCriteriaBuilder
{
    protected Criteria $criteria;

    public function __construct(ExportContext $exportContext)
    {
        parent::__construct($exportContext);

        $this->exportContext = $exportContext;
    }

    public function reset(): void
    {
        $this->criteria = new Criteria();
    }

    public function build(): Criteria
    {
        $criteria = clone $this->criteria;
        $this->reset();

        return $criteria;
    }

    public function withLimit(?int $limit): ProductCriteriaBuilder
    {
        if ($limit) {
            $this->criteria->setLimit($limit);
        }

        return $this;
    }

    public function withOffset(?int $offset): ProductCriteriaBuilder
    {
        if ($offset) {
            $this->criteria->setOffset($offset);
        }

        return $this;
    }

    public function withCreatedAtSorting(): ProductCriteriaBuilder
    {
        $this->criteria->addSorting(new FieldSorting('createdAt'));

        return $this;
    }

    public function withIdSorting(): ProductCriteriaBuilder
    {
        $this->criteria->addSorting(new FieldSorting('id'));

        return $this;
    }

    public function withPriceSorting(): ProductCriteriaBuilder
    {
        $this->criteria->addSorting(new FieldSorting('price'));

        return $this;
    }

    public function withIds(array $ids): ProductCriteriaBuilder
    {
        $this->criteria->setIds($ids);

        return $this;
    }

    public function withOutOfStockFilter(): ProductCriteriaBuilder
    {
        if (!$this->exportContext->shouldHideProductsOutOfStock()) {
            return $this;
        }

        $this->criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('product.isCloseout', true),
                    new EqualsFilter('product.available', false),
                ]
            )
        );

        return $this;
    }

    public function withVisibilityFilter(): ProductCriteriaBuilder
    {
        $this->criteria->addFilter(
            new ProductAvailableFilter(
                $this->exportContext->getSalesChannelId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        return $this;
    }

    public function withDisplayGroupFilter(): ProductCriteriaBuilder
    {
        $this->criteria->addGroupField(new FieldGrouping('displayGroup'));
        $this->criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('displayGroup', null)]
            )
        );

        return $this;
    }

    public function withParentIdFilter(string $parentId): ProductCriteriaBuilder
    {
        $this->criteria->addFilter(new EqualsFilter('parentId', $parentId));

        return $this;
    }

    public function withProductIdFilter(?string $productId, ?bool $considerVariants = false): ProductCriteriaBuilder
    {
        if ($productId) {
            $productFilter = [
                new EqualsFilter('ean', $productId),
                new EqualsFilter('manufacturerNumber', $productId),
                new EqualsFilter('productNumber', $productId),
            ];

            // Only add the id filter in case the provided value is a valid uuid, to prevent Shopware
            // from throwing an exception in case it is not.
            if (Uuid::isValid($productId)) {
                $productFilter[] = new EqualsFilter('id', $productId);

                if ($considerVariants) {
                    $productFilter[] = new EqualsFilter('parentId', $productId);
                }
            }

            $this->criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    $productFilter
                )
            );
        }

        return $this;
    }

    public function withProductAssociations(): ProductCriteriaBuilder
    {
        $this->criteria->addAssociations(
            array_merge(Constants::PRODUCT_ASSOCIATIONS, Constants::VARIANT_ASSOCIATIONS),
        );

        return $this;
    }

    public function withVariantAssociations(): ProductCriteriaBuilder
    {
        $this->criteria->addAssociations(Constants::VARIANT_ASSOCIATIONS);

        return $this;
    }

    public function withPriceZeroFilter(): ProductCriteriaBuilder
    {
        $this->criteria->addFilter(
            new RangeFilter('price', [
                RangeFilter::GT => 0
            ])
        );

        return $this;
    }

    public function withActiveParentOrInactiveParentWithVariantsFilter(): ProductCriteriaBuilder
    {
        $notActiveOrPriceZeroFilter = new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('active', false),
                new EqualsFilter('price', 0)
            ]
        );

        $activeParentFilter =  new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('parentId', null),
                new EqualsFilter('active', true),
                new RangeFilter('price', [RangeFilter::GT => 0])
            ]
        );

        /**
         * We still need to fetch the product if it is not active, but has child products.
         */
        $inactiveParentWithChildrenFilter = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('parentId', null),
                new RangeFilter('childCount', [RangeFilter::GT => 0]),
                $notActiveOrPriceZeroFilter
            ]
        );

        $this->criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    $activeParentFilter,
                    $inactiveParentWithChildrenFilter
                ]
            )
        );

        return $this;
    }

    protected function adaptProductIdCriteriaWithParentId(string $productId): void
    {
        $this->criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('parentId', $productId),
                    $this->getVisibilityFilterForRealVariants()
                ]
            )
        );
    }

    protected function adaptProductIdCriteriaWithoutParentId(string $productId, string $parentId): void
    {
        $this->criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new MultiFilter(
                        MultiFilter::CONNECTION_AND,
                        [
                            new EqualsFilter('parentId', $parentId),
                            $this->getVisibilityFilterForRealVariants()
                        ]
                    ),
                    new EqualsFilter('id', $parentId)
                ]
            )
        );

        $this->criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('id', $productId)])
        );
    }

    protected function getVisibilityFilterForRealVariants(): ProductAvailableFilter
    {
        return new ProductAvailableFilter(
            $this->exportContext->getSalesChannelId(),
            ProductVisibilityDefinition::VISIBILITY_SEARCH
        );
    }
}
