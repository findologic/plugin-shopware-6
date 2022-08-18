<?php

declare(strict_types = 1);

namespace FINDOLOGIC\FinSearch\Export\Provider;

use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\Search\CustomerSearcher;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomerGroupSalesChannelProvider
{
    /** @var SalesChannelService */
    private $salesChannelService;

    /** @var CustomerSearcher */
    private $customerSearcher;

    /**
     * @var AdvancedPriceRulesProvider
     */
    private $advancedPriceRulesProvider;

    public function __construct(
        AdvancedPriceRulesProvider $advancedPriceRulesProvider,
        SalesChannelService $salesChannelService,
        CustomerSearcher $customerSearcher
    ) {
        $this->salesChannelService = $salesChannelService;
        $this->customerSearcher = $customerSearcher;
        $this->advancedPriceRulesProvider = $advancedPriceRulesProvider;
    }

    public function getSalesChannelForUserGroup(
        SalesChannelContext $salesChannelContext,
        string $customerGroup,
        string $shopKey
    ): ?SalesChannelContext {
        $customerId = $this->customerSearcher->getSingleCustomerIdByGroup(
            $salesChannelContext,
            $customerGroup
        );

        if (!$customerId) {
            return null;
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
