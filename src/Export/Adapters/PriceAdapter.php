<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Export\Providers\CustomerGroupContextProvider;
use FINDOLOGIC\FinSearch\Export\Traits\SupportsAdvancedPricing;
use FINDOLOGIC\Shopware6Common\Export\Adapters\PriceAdapter as CommonPriceAdapter;
use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\Exceptions\Product\ProductHasNoPricesException;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Utils\Utils as CommonUtils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as CurrencyPrice;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupEntity;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity as SdkProductEntity;

class PriceAdapter extends CommonPriceAdapter
{
    use SupportsAdvancedPricing;

    public function __construct(
        protected readonly ProductPriceCalculator $calculator,
        protected readonly CustomerGroupContextProvider $customerGroupContextProvider,
        ExportContext $exportContext,
        PluginConfig $pluginConfig,
        SalesChannelContext $salesChannelContext,
        SalesChannelRepository $salesChannelProductRepository,
        string $shopwareVersion,
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->salesChannelProductRepository = $salesChannelProductRepository;
        $this->shopwareVersion = $shopwareVersion;

        parent::__construct($exportContext, $pluginConfig);
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

        if (!$currencyPrice = $this->getCurrencyPrice($product)) {
            return [];
        }

        if (!$this->pluginConfig->useXmlVariants()) {
            foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
                if ($price = $this->getStandardPrice($currencyPrice, $customerGroup)) {
                    $prices[] = $price;
                }
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

        if (!$this->pluginConfig->useXmlVariants()) {
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
        if (!$advancedPrice = $this->calculateAdvancedPrice($product, $customerGroupId)) {
            return null;
        }

        $price = new Price();

        if ($customerGroupId && !$this->pluginConfig->useXmlVariants()) {
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
}
