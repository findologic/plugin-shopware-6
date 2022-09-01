<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Providers;

use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AdvancedPriceRulesProvider
{
    /** @var CartRuleLoader */
    private $cartRuleLoader;

    public function __construct(CartRuleLoader $cartRuleLoader)
    {
        $this->cartRuleLoader = $cartRuleLoader;
    }

    public function getMatchingRulesIds(SalesChannelContext $salesChannelContext): array
    {
        $rules = $this->cartRuleLoader->loadByToken($salesChannelContext, $salesChannelContext->getToken());
        $ruleIds = [];

        foreach ($rules->getMatchingRules() as $rule) {
            $ruleIds[] = $rule->getId();
        }

        return $ruleIds;
    }
}
