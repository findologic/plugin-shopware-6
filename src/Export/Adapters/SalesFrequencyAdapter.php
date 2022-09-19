<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use DateTimeImmutable;
use FINDOLOGIC\Shopware6Common\Export\Adapters\AbstractSalesFrequencyAdapter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity;

class SalesFrequencyAdapter extends AbstractSalesFrequencyAdapter
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

    protected function buildCriteria(ProductEntity $product): Criteria
    {
        $lastMonthDate = new DateTimeImmutable('-1 month');
        $criteria = new Criteria();
        $criteria->addAssociation('order');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('productId', $product->id),
            new RangeFilter(
                'order.orderDateTime',
                [RangeFilter::GTE => $lastMonthDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            )
        ]));

        return $criteria;
    }

    protected function getOrderCount(ProductEntity $product): int
    {
        $orders = $this->orderLineItemRepository->searchIds(
            $this->buildCriteria($product),
            $this->salesChannelContext->getContext()
        );

        return $orders->getTotal();
    }
}
