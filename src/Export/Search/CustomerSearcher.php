<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomerSearcher
{
    private EntityRepositoryInterface $customerRepository;

    public function __construct(
        EntityRepositoryInterface $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
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
