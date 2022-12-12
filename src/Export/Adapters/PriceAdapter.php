<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Export\Providers\CustomerGroupContextProvider;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Adapters\PriceAdapter as CommonPriceAdapter;
use FINDOLOGIC\Shopware6Common\Export\Config\AdvancedPricing;
use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\Exceptions\Product\ProductHasNoPricesException;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Utils\Utils as CommonUtils;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as CurrencyPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupEntity;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity as SdkProductEntity;

class PriceAdapter extends CommonPriceAdapter
{
    protected SalesChannelContext $salesChannelContext;

    protected ProductPriceCalculator $calculator;

    protected CustomerGroupContextProvider $customerGroupContextProvider;

    protected EntityRepository $productRepository;

    protected PluginConfig $config;

    protected string $shopwareVersion;

    public function __construct(
        ExportContext $exportContext,
        SalesChannelContext $salesChannelContext,
        ProductPriceCalculator $productPriceCalculator,
        CustomerGroupContextProvider $customerGroupContextProvider,
        EntityRepository $productRepository,
        PluginConfig $config,
        string $shopwareVersion
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->calculator = $productPriceCalculator;
        $this->customerGroupContextProvider = $customerGroupContextProvider;
        $this->productRepository = $productRepository;
        $this->config = $config;
        $this->shopwareVersion = $shopwareVersion;

        parent::__construct($exportContext);
    }

    /**
     * @return Price[]
     * @throws ProductHasNoPricesException
     */
    public function adapt(SdkProductEntity $product): array
    {
        $shopwareProduct = $this->getShopwareProduct($product->id);

        $prices = $this->useAdvancedPricing()
            ? $this->getAdvancedPricesFromProduct($shopwareProduct)
            : $this->getPriceFromProduct($shopwareProduct);

        if (CommonUtils::isEmpty($prices)) {
            throw new ProductHasNoPricesException($product);
        }

        return $prices;
    }

    /**
     * @return Price[]
     */
    protected function getPriceFromProduct(ProductEntity $product): array
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
            if (!$price = $this->getAdvancedPrice($product, $customerGroup->id)) {
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

        return empty($prices) ? $this->getPriceFromProduct($product) : $prices;
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
            $price->setValue(round($advancedPrice->getUnitPrice(), 2), $customerGroupId);
        } else {
            $price->setValue(round($advancedPrice->getUnitPrice(), 2));
        }

        return $price;
    }

    protected function getStandardPrice(CurrencyPrice $currencyPrice, CustomerGroupEntity $customerGroup): ?Price
    {
        if (CommonUtils::isEmpty($customerGroup->id)) {
            return null;
        }

        $netPrice = $currencyPrice->getNet();
        $grossPrice = $currencyPrice->getGross();
        $price = new Price();

        if ($customerGroup->displayGross) {
            $price->setValue(round($grossPrice, 2), $customerGroup->id);
        } else {
            $price->setValue(round($netPrice, 2), $customerGroup->id);
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

    protected function getShopwareProduct(string $productId): ProductEntity
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices.rule.conditions');

        return $this->productRepository->search(
            $criteria,
            $this->salesChannelContext->getContext(),
        )->first();
    }
}
