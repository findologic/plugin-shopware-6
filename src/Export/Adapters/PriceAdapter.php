<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\Provider\CustomerGroupSalesChannelProvider;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PriceAdapter
{
    protected const WHITELISTED_RULE_CONDITIONS = [
        'customerCustomerGroup',
        'orContainer',
        'andContainer'
    ];

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var ExportContext */
    protected $exportContext;

    /** @var ProductPriceCalculator */
    protected $calculator;
    /**
     * @var CustomerGroupSalesChannelProvider
     */
    private $customerGroupSalesChannelProvider;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        ExportContext $exportContext,
        ProductPriceCalculator $productPriceCalculator,
        CustomerGroupSalesChannelProvider $customerGroupSalesChannelProvider
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->exportContext = $exportContext;
        $this->calculator = $productPriceCalculator ;
        $this->customerGroupSalesChannelProvider = $customerGroupSalesChannelProvider;
    }

    /**
     * @return Price[]
     * @throws ProductHasNoPricesException
     */
    public function adapt(ProductEntity $product): array
    {
        $prices = $this->getAdvancedPricesFromProduct($product);
        if (Utils::isEmpty($prices)) {
            throw new ProductHasNoPricesException($product);
        }

        return $prices;
    }

    /**
     * @return Price[]
     */
    protected function getPricesFromProduct(ProductEntity $product): array
    {
        $prices = [];
        $productPrice = $product->getPrice();
        if (!$productPrice || !$productPrice->first()) {
            return [];
        }

        $currencyId = $this->salesChannelContext->getSalesChannel()->getCurrencyId();
        $currencyPrice = $productPrice->getCurrencyPrice($currencyId, false);

        // If no currency price is available, fallback to the default price.
        if (!$currencyPrice) {
            $currencyPrice = $productPrice->first();
        }

        foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
            $userGroupHash = Utils::calculateUserGroupHash($this->exportContext->getShopkey(), $customerGroup->getId());
            if (Utils::isEmpty($userGroupHash)) {
                continue;
            }

            $netPrice = $currencyPrice->getNet();
            $grossPrice = $currencyPrice->getGross();
            $price = new Price();
            if ($customerGroup->getDisplayGross()) {
                $price->setValue(round($grossPrice, 2), $userGroupHash);
            } else {
                $price->setValue(round($netPrice, 2), $userGroupHash);
            }

            $prices[] = $price;
        }

        $price = new Price();
        $price->setValue(round($currencyPrice->getGross(), 2));
        $prices[] = $price;

        return $prices;
    }

    /**
     * @return Price[]
     */
    public function getAdvancedPricesFromProduct(ProductEntity $product): array
    {
        foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
           $salesChannelContext = $this->customerGroupSalesChannelProvider->getSalesChannelForUserGroup(
               $this->salesChannelContext,
               $customerGroup->getId(),
               $this->exportContext->getShopkey()
           );

           if (!$salesChannelContext) {
               continue;
           }

            $this->calculator->calculate([$product], $salesChannelContext);

            dd($product->get('calculatedPrices'));
        }

        $this->calculator->calculate([$product], $salesChannelContext);

        $prices = [];
        $productPrice = $product->getPrice();
        $productPrices = $product->getPrices() ? $product->getPrices()->getElements() : null;

        $foundRules = [];
        $cheapestCustomerGroupPrices = $product->getPrices()->filter(function (ProductPriceEntity $price) use (&$foundRules) {
            /** @var RuleConditionEntity $condition */
            foreach ($price->getRule()->getConditions() as $condition) {
                if (!in_array($condition->getType(), self::WHITELISTED_RULE_CONDITIONS)) {
                    return false;
                }
            }

            if ($price->getQuantityStart() > 1) {
                return false;
            }

            $currentPrice = $price->getPrice()->first()->getGross();
            dump($foundRules);
            if (key_exists($price->getRuleId(), $foundRules)) {
                $foundPrice = $foundRules[$price->getRuleId()];

                if ($foundPrice > $currentPrice) {
                    $foundRules[$price->getRuleId()] = $currentPrice;
                } else {
                    return false;
                }
            } else {
                $foundRules[$price->getRuleId()] = $currentPrice;
            }
            return true;
        });

        dump($cheapestCustomerGroupPrices);

        if (!$productPrice || !$productPrice->first()) {
            return [];
        }

        // Sorting in the criteria is not working in combination of the condition filter
        usort($productPrices, function(ProductPriceEntity $a, ProductPriceEntity $b) {
            return $a->getRule()->getPriority() < $b->getRule()->getPriority();
        });

        $calculatedPrices = $product->get('calculatedPrices');
        usort($productPrices, function(ProductPriceEntity $a, ProductPriceEntity $b) {
            return $a->getRule()->getPriority() < $b->getRule()->getPriority();
        });

        dd($product->getId(), $product->get('calculatedPrices'), $productPrices);
    }
}
