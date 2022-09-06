<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\Providers\CustomerGroupContextProvider;
use FINDOLOGIC\FinSearch\Findologic\AdvancedPricing;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as CurrencyPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PriceAdapter
{
    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var ExportContext */
    protected $exportContext;

    /** @var ProductPriceCalculator */
    protected $calculator;

    /** @var CustomerGroupContextProvider */
    private $customerGroupContextProvider;

    /** @var Config */
    private $config;

    /** @var string */
    private $shopwareVersion;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        ExportContext $exportContext,
        ProductPriceCalculator $productPriceCalculator,
        CustomerGroupContextProvider $customerGroupContextProvider,
        Config $config,
        string $shopwareVersion
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->exportContext = $exportContext;
        $this->calculator = $productPriceCalculator;
        $this->customerGroupContextProvider = $customerGroupContextProvider;
        $this->config = $config;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @return Price[]
     * @throws ProductHasNoPricesException
     */
    public function adapt(ProductEntity $product): array
    {
        $prices = $this->useAdvancedPricing()
            ? $this->getAdvancedPricesFromProduct($product)
            : $this->getPricesFromProduct($product);

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

        $currencyPrice = $this->getCurrencyPrice($product);

        if (!$currencyPrice) {
            return [];
        }

        foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
            if ($price = $this->getStandardPrice($currencyPrice, $customerGroup)) {
                $prices[] = $price;
            }
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
        $prices = [];

        foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
            // If no advanced price is provided - use standard price
            if (!$price = $this->getAdvancedPrice($product, $customerGroup->getId())) {
                if (!$currencyPrice = $this->getCurrencyPrice($product)) {
                    continue;
                }

                $price = $this->getStandardPrice($currencyPrice, $customerGroup);
                if (!$price) {
                    continue;
                }
            }

            $prices[] = $price;
        }

        // If no advanced price is provided - use standard price
        if (!$price = $this->getAdvancedPrice($product, null)) {
            if ($currencyPrice = $this->getCurrencyPrice($product)) {
                $price = new Price();
                $price->setValue(round($currencyPrice->getGross(), 2));
            }
        }

        if ($price) {
            $prices[] = $price;
        }

        return empty($prices) ? $this->getPricesFromProduct($product) : $prices;
    }

    protected function getAdvancedPrice(ProductEntity $product, ?string $customerGroupId): ?Price
    {
        $salesChannelContext = $this->customerGroupContextProvider->getSalesChannelForUserGroup(
            $this->salesChannelContext,
            $customerGroupId,
            $this->exportContext->getShopkey()
        );

        if (!$salesChannelContext) {
            return null;
        }

        $this->calculator->calculate([$product], $salesChannelContext);

        if ($product->get('calculatedPrices')->count() === 0) {
            return null;
        }

        $advancedPrice = $this->getPriceBasedOnConfiguration(
            $product->get('calculatedPrices')
        );

        if (!$advancedPrice) {
            return null;
        }

        $price = new Price();

        if ($customerGroupId) {
            $userGroupHash = Utils::calculateUserGroupHash($this->exportContext->getShopkey(), $customerGroupId);
            $price->setValue(round($advancedPrice->getUnitPrice(), 2), $userGroupHash);
        } else {
            $price->setValue(round($advancedPrice->getUnitPrice(), 2));
        }

        return $price;
    }

    protected function getStandardPrice(CurrencyPrice $currencyPrice, CustomerGroupEntity $customerGroup): ?Price
    {
        $userGroupHash = Utils::calculateUserGroupHash($this->exportContext->getShopkey(), $customerGroup->getId());

        if (Utils::isEmpty($userGroupHash)) {
            return null;
        }

        $netPrice = $currencyPrice->getNet();
        $grossPrice = $currencyPrice->getGross();
        $price = new Price();

        if ($customerGroup->getDisplayGross()) {
            $price->setValue(round($grossPrice, 2), $userGroupHash);
        } else {
            $price->setValue(round($netPrice, 2), $userGroupHash);
        }

        return $price;
    }

    protected function getCurrencyPrice(ProductEntity $product): ?CurrencyPrice
    {
        $productPrice = $product->getPrice();

        if (!$productPrice || !$productPrice->first()) {
            return null;
        }

        $currencyId = $this->salesChannelContext->getSalesChannel()->getCurrencyId();
        $currencyPrice = $productPrice->getCurrencyPrice($currencyId, false);

         return $currencyPrice ?? $productPrice->first();
    }

    protected function getPriceBasedOnConfiguration(PriceCollection $priceCollection): ?CalculatedPrice
    {
        if ($this->config->getAdvancedPricing() === AdvancedPricing::OFF) {
            return null;
        } elseif ($this->config->getAdvancedPricing() === AdvancedPricing::UNIT) {
            return $priceCollection->first();
        }

        return $this->getCheapestPrice($priceCollection);
    }

    protected function getCheapestPrice(PriceCollection $priceCollection): CalculatedPrice
    {
        $priceCollection->sort(function (CalculatedPrice $a, CalculatedPrice $b) {
            return  $a->getUnitPrice() <=> $b->getUnitPrice();
        });

        return $priceCollection->first();
    }

    protected function useAdvancedPricing(): bool
    {
        return Utils::versionGreaterOrEqual('6.4.9.0', $this->shopwareVersion)
            && $this->config->getAdvancedPricing() !== AdvancedPricing::OFF;
    }
}
