<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductCriteriaBuilder
{
    protected SalesChannelContext $salesChannelContext;

    protected SystemConfigService $systemConfigService;

    protected Criteria $criteria;

    protected Config $config;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        SystemConfigService $systemConfigService,
        Config $config
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->systemConfigService = $systemConfigService;
        $this->config = $config;
        $this->reset();
    }

    public function reset(): void
    {
        $this->criteria = new Criteria();
    }

    public function setConfig(Config $config): void
    {
        $this->config = $config;
    }

    public function build(): Criteria
    {
        $criteria = clone $this->criteria;
        $this->reset();

        return $criteria;
    }

    public function fromCriteria(Criteria $criteria): self
    {
        $this->criteria = clone $criteria;

        return $this;
    }

    public function withDefaultCriteria(?int $limit = null, ?int $offset = null, ?string $productId = null): self
    {
        $this->withCreatedAtSorting()
            ->withIdSorting()
            ->withPagination($limit, $offset)
            ->withProductIdFilter($productId)
            ->withOutOfStockFilter()
            ->withProductAssociations();

        return $this;
    }

    public function withChildCriteria(string $parentId): self
    {
        $this->criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $this->withPriceSorting()
            ->withCreatedAtSorting()
            ->withLimit(1)
            ->withOutOfStockFilter()
            ->withPriceZeroFilter()
            ->withVisibilityFilter();

        return $this;
    }

    public function withPagination(?int $limit, ?int $offset): self
    {
        $this->withLimit($limit);
        $this->withOffset($offset);

        return $this;
    }

    public function withLimit(?int $limit): self
    {
        if ($limit) {
            $this->criteria->setLimit($limit);
        }

        return $this;
    }

    public function withOffset(?int $offset): self
    {
        if ($offset) {
            $this->criteria->setOffset($offset);
        }

        return $this;
    }

    public function withCreatedAtSorting(): self
    {
        $this->criteria->addSorting(new FieldSorting('createdAt'));

        return $this;
    }

    public function withIdSorting(): self
    {
        $this->criteria->addSorting(new FieldSorting('id'));

        return $this;
    }

    public function withPriceSorting(): self
    {
        $this->criteria->addSorting(new FieldSorting('price'));

        return $this;
    }

    public function withIds(array $ids): self
    {
        $this->criteria->setIds($ids);

        return $this;
    }

    public function withOutOfStockFilter(): self
    {
        if (!$this->shouldHideProductsOutOfStock()) {
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

    protected function shouldHideProductsOutOfStock(): bool
    {
        $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();

        return !!$this->systemConfigService->get(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $salesChannelId
        );
    }

    public function withVisibilityFilter(): self
    {
        $this->criteria->addFilter(
            new ProductAvailableFilter(
                $this->salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        return $this;
    }

    public function withDisplayGroupFilter(): self
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

    public function withProductIdFilter(?string $productId, ?bool $considerVariants = false): self
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

    public function withProductAssociations(): self
    {
        Utils::addProductAssociations($this->criteria);

        return $this;
    }

    public function withVariantAssociations(): self
    {
        Utils::addVariantAssociations($this->criteria);

        return $this;
    }

    public function withPriceZeroFilter(): self
    {
        if ($this->config->shouldExportZeroPricedProducts()) {
            return $this;
        }

        $this->criteria->addFilter(
            new RangeFilter('price', [
                RangeFilter::GT => 0
            ])
        );

        return $this;
    }

    public function withActiveParentOrInactiveParentWithVariantsFilter(): self
    {
        $notActiveOrPriceZeroFilter = new MultiFilter(
            MultiFilter::CONNECTION_OR,
            $this->getNotActiveFilterBasedOnPriceConfiguration()
        );

        $activeParentFilter =  new MultiFilter(
            MultiFilter::CONNECTION_AND,
            $this->getActiveFilterBasedOnPriceConfiguration()
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

    public function withParentIdFilterWithVisibility(ProductEntity $productEntity): self
    {
        if (!$productEntity->getParentId()) {
            $this->adaptProductIdCriteriaWithParentId($productEntity);
        } else {
            $this->adaptProductIdCriteriaWithoutParentId($productEntity);
        }

        return $this;
    }

    protected function adaptProductIdCriteriaWithParentId(ProductEntity $productEntity): void
    {
        $this->criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('parentId', $productEntity->getId()),
                    $this->getVisibilityFilterForRealVariants()
                ]
            )
        );
    }

    protected function getNotActiveFilterBasedOnPriceConfiguration(): array
    {
        $notActiveFilter = [
            new EqualsFilter('active', false)
        ];

        if (!$this->config->shouldExportZeroPricedProducts()) {
            $notActiveFilter[] = new EqualsFilter('price', 0);
        }

        return $notActiveFilter;
    }

    protected function getActiveFilterBasedOnPriceConfiguration(): array
    {
        $activeFilter = [
            new EqualsFilter('parentId', null),
            new EqualsFilter('active', true),
        ];

        if (!$this->config->shouldExportZeroPricedProducts()) {
            $activeFilter[] = new RangeFilter('price', [RangeFilter::GT => 0]);
        }

        return $activeFilter;
    }

    protected function adaptProductIdCriteriaWithoutParentId(ProductEntity $productEntity): void
    {
        $this->criteria->addFilter(
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

        $this->criteria->addFilter(
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

    public function withAdvancedPricing(): self
    {
        $this->criteria->addAssociation('prices.rule.conditions');

        return $this;
    }
}
