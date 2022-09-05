<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use DateTimeImmutable;
use FINDOLOGIC\Export\Data\SalesFrequency;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesFrequencyAdapter
{
    protected EntityRepository $orderLineItemRepository;

    protected SalesChannelContext $salesChannelContext;

    public function __construct(
        EntityRepository $orderLineItemRepository,
        SalesChannelContext $salesChannelContext
    ) {
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function adapt(ProductEntity $product): ?SalesFrequency
    {
        $orders = $this->orderLineItemRepository->searchIds(
            $this->buildCriteria($product),
            $this->salesChannelContext->getContext()
        );

        $salesFrequency = new SalesFrequency();
        $salesFrequency->setValue($orders->getTotal());

        return $salesFrequency;
    }

    protected function buildCriteria(ProductEntity $product): Criteria
    {
        $lastMonthDate = new DateTimeImmutable('-1 month');
        $criteria = new Criteria();
        $criteria->addAssociation('order');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('productId', $product->getId()),
            new RangeFilter(
                'order.orderDateTime',
                [RangeFilter::GTE => $lastMonthDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            )
        ]));

        return $criteria;
    }
}
