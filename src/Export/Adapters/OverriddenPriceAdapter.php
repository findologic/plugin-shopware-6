<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\OverriddenPrice;
use FINDOLOGIC\FinSearch\Export\Providers\CustomerGroupContextProvider;
use FINDOLOGIC\FinSearch\Export\Traits\SupportsAdvancedPricing;
use FINDOLOGIC\Shopware6Common\Export\Adapters\OverriddenPriceAdapter as CommonOverriddenPriceAdapter;
use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Utils\Utils as CommonUtils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as CurrencyPrice;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupEntity;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity as SdkProductEntity;

class OverriddenPriceAdapter extends CommonOverriddenPriceAdapter
{
    use SupportsAdvancedPricing;

    public function __construct(
        protected readonly ProductPriceCalculator $calculator,
        protected readonly CustomerGroupContextProvider $customerGroupContextProvider,
        protected readonly ExportContext $exportContext,
        protected readonly PluginConfig $pluginConfig,
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
     * @return OverriddenPrice[]
     */
    public function adapt(SdkProductEntity $product): array
    {
        $shopwareProduct = $this->getShopwareProduct($product->id);

        return $this->useAdvancedPricing()
            ? $this->getAdvancedPricesFromProduct($shopwareProduct)
            : $this->getPriceFromProduct($shopwareProduct);
    }

    /**
     * @return OverriddenPrice[]
     */
    protected function getPriceFromProduct(ProductEntity $product): array
    {
        $overriddenPrices = [];

        if (!$listPrice = $this->getCurrencyPrice($product)?->getListPrice()) {
            return [];
        }

        if (!$this->pluginConfig->useXmlVariants()) {
            foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
                if ($price = $this->getStandardPrice($listPrice, $customerGroup)) {
                    $overriddenPrices[] = $price;
                }
            }
        }

        $overriddenPrice = new OverriddenPrice();
        $overriddenPrice->setValue(round($listPrice->getGross(), 2));
        $overriddenPrices[] = $overriddenPrice;

        return $overriddenPrices;
    }

    /**
     * @return OverriddenPrice[]
     */
    public function getAdvancedPricesFromProduct(ProductEntity $product): array
    {
        $prices = [];

        if (!$this->pluginConfig->useXmlVariants()) {
            foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
                // If no advanced price is provided - use standard price
                if (!$price = $this->getAdvancedPrice($product, $customerGroup->id)) {
                    if (!$listPrice = $this->getCurrencyPrice($product)?->getListPrice()) {
                        continue;
                    }

                    $price = $this->getStandardPrice($listPrice, $customerGroup);
                    if (!$price) {
                        continue;
                    }
                }

                $prices[] = $price;
            }
        }

        // If no advanced price is provided - use standard price
        if (!$price = $this->getAdvancedPrice($product, null)) {
            if ($listPrice = $this->getCurrencyPrice($product)?->getListPrice()) {
                $price = new OverriddenPrice();
                $price->setValue(round($listPrice->getGross(), 2));
            }
        }

        if ($price) {
            $prices[] = $price;
        }

        return empty($prices) ? $this->getPriceFromProduct($product) : $prices;
    }

    protected function getAdvancedPrice(ProductEntity $product, ?string $customerGroupId): ?OverriddenPrice
    {
        if (!$listPrice = $this->calculateAdvancedPrice($product, $customerGroupId)?->getListPrice()) {
            return null;
        }

        $overriddenPrice = new OverriddenPrice();

        if ($customerGroupId && !$this->pluginConfig->useXmlVariants()) {
            $overriddenPrice->setValue(round($listPrice->getPrice(), 2), $customerGroupId);
        } else {
            $overriddenPrice->setValue(round($listPrice->getPrice(), 2));
        }

        return $overriddenPrice;
    }

    protected function getStandardPrice(CurrencyPrice $listPrice, CustomerGroupEntity $customerGroup): ?OverriddenPrice
    {
        if (CommonUtils::isEmpty($customerGroup->id)) {
            return null;
        }

        $netPrice = $listPrice->getNet();
        $grossPrice = $listPrice->getGross();
        $overriddenPrice = new OverriddenPrice();

        if ($customerGroup->displayGross) {
            $overriddenPrice->setValue(round($grossPrice, 2), $customerGroup->id);
        } else {
            $overriddenPrice->setValue(round($netPrice, 2), $customerGroup->id);
        }

        return $overriddenPrice;
    }
}
