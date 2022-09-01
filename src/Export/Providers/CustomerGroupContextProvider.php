<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Providers;

use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\Search\CustomerSearcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomerGroupContextProvider
{
    /** @var SalesChannelService */
    private $salesChannelService;

    /** @var CustomerSearcher */
    private $customerSearcher;

    /** @var AdvancedPriceRulesProvider */
    private $advancedPriceRulesProvider;

    public function __construct(
        AdvancedPriceRulesProvider $advancedPriceRulesProvider,
        SalesChannelService $salesChannelService,
        CustomerSearcher $customerSearcher
    ) {
        $this->advancedPriceRulesProvider = $advancedPriceRulesProvider;
        $this->salesChannelService = $salesChannelService;
        $this->customerSearcher = $customerSearcher;
    }

    public function getSalesChannelForUserGroup(
        SalesChannelContext $salesChannelContext,
        ?string $customerGroup,
        string $shopKey
    ): ?SalesChannelContext {
        $customerId = null;

        if ($customerGroup) {
            $customerId = $this->customerSearcher->getSingleCustomerIdByGroup(
                $salesChannelContext,
                $customerGroup
            );

            if (!$customerId) {
                return null;
            }
        }

        $salesChannelContext = $this->salesChannelService->getSalesChannelContext(
            $salesChannelContext,
            $shopKey,
            $customerId
        );

        $ruleIds = $this->advancedPriceRulesProvider->getMatchingRulesIds($salesChannelContext);

        $salesChannelContext->setRuleIds($ruleIds);

        return $salesChannelContext;
    }
}
