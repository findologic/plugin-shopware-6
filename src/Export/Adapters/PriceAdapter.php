<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\Provider\CustomerGroupSalesChannelProvider;
use FINDOLOGIC\FinSearch\Export\Provider\PriceBasedOnConfigurationProvider;
use FINDOLOGIC\FinSearch\Findologic\AdvancedPricing;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
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

    /** @var CustomerGroupSalesChannelProvider */
    private $customerGroupSalesChannelProvider;

    /** @var PriceBasedOnConfigurationProvider */
    private $priceBasedOnConfigurationProvider;

    /** @var Config */
    private $config;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        ExportContext $exportContext,
        ProductPriceCalculator $productPriceCalculator,
        CustomerGroupSalesChannelProvider $customerGroupSalesChannelProvider,
        PriceBasedOnConfigurationProvider $priceBasedOnConfigurationProvider,
        Config $config
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->exportContext = $exportContext;
        $this->calculator = $productPriceCalculator ;
        $this->customerGroupSalesChannelProvider = $customerGroupSalesChannelProvider;
        $this->priceBasedOnConfigurationProvider = $priceBasedOnConfigurationProvider;
        $this->config = $config;
    }

    /**
     * @return Price[]
     * @throws ProductHasNoPricesException
     */
    public function adapt(ProductEntity $product): array
    {
        if ($this->config->getAdvancedPricing() === AdvancedPricing::OFF) {
            $prices = $this->getPricesFromProduct($product);
        } else {
            $prices = $this->getAdvancedPricesFromProduct($product);
        }

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

        $currencyPrice = $this->getCurrencyPrice($product, $this->salesChannelContext);

        foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
            $price = $this->getStandardPrice($currencyPrice, $customerGroup);

            if (!$price) {
                continue;
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
        $prices = [];

        foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
            $price = $this->getAdvancedPrice($product, $customerGroup->getId());

            // If no advanced price is provided - use standard price
            if (!$price) {
               $currencyPrice = $this->getCurrencyPrice($product);
               $price = $this->getStandardPrice($currencyPrice, $customerGroup);

               if (!$price) {
                   continue;
               }
            }

            $prices[] = $price;
        }

        $price = $this->getAdvancedPrice($product, null);

        // If no advanced price is provided - use standard price
        if (!$price) {
            $currencyPrice = $this->getCurrencyPrice($product);
            $price = new Price();
            $price->setValue(round($currencyPrice->getGross(), 2));
        }

        if ($price) {
            $prices[] = $price;
        }

        return empty($prices) ? $this->getPricesFromProduct($product) : $prices;
    }

    protected function getAdvancedPrice(ProductEntity $product, ?string $customerGroupId): ?Price
    {
        $salesChannelContext = $this->customerGroupSalesChannelProvider->getSalesChannelForUserGroup(
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

        $advancedPrice = $this->priceBasedOnConfigurationProvider->getPriceBasedOnConfiguration(
            $product->get('calculatedPrices')
        );

        $price = new Price();

        if (!$customerGroupId) {
            $price->setValue(round($advancedPrice->getUnitPrice(), 2));

            return $price;
        }

        $userGroupHash = Utils::calculateUserGroupHash($this->exportContext->getShopkey(), $customerGroupId);
        $price->setValue(round($advancedPrice->getUnitPrice(), 2), $userGroupHash);

        return $price;
    }

    protected function getStandardPrice(CurrencyPrice $currencyPrice, CustomerGroupEntity $customerGroup): ? Price
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

        // If no currency price is available, fallback to the default price.
        if (!$currencyPrice) {
            $currencyPrice = $productPrice->first();
        }

        return $currencyPrice;
    }
}
