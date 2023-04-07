<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomerSearcher
{
    public function __construct(
        protected readonly EntityRepository $customerRepository
    ) {
    }

    public function getSingleCustomerIdByGroup(
        SalesChannelContext $salesChannelContext,
        string $customerGroupId
    ): ?string {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(
            new EqualsFilter(
                'groupId',
                $customerGroupId
            )
        );

        return $this->customerRepository
            ->searchIds($criteria, $salesChannelContext->getContext())
            ->firstId();
    }
}
