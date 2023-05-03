<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Traits;

use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\Enums\AdvancedPricing;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as CurrencyPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait SupportsAdvancedPricing
{
    protected readonly SalesChannelContext $salesChannelContext;

    protected readonly SalesChannelRepository $salesChannelProductRepository;

    protected PluginConfig $pluginConfig;

    protected readonly string $shopwareVersion;

    protected function calculateAdvancedPrice(ProductEntity $product, ?string $customerGroupId): ?CalculatedPrice
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

        return $this->getPriceBasedOnConfiguration(
            $product->get('calculatedPrices')
        );
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
        if ($this->pluginConfig->getAdvancedPricing() === AdvancedPricing::OFF) {
            return null;
        } elseif ($this->pluginConfig->getAdvancedPricing() === AdvancedPricing::UNIT) {
            return $priceCollection->first();
        }

        return $this->getCheapestPrice($priceCollection);
    }

    protected function getCheapestPrice(PriceCollection $priceCollection): CalculatedPrice
    {
        $priceCollection->sort(function (CalculatedPrice $a, CalculatedPrice $b) {
            return $a->getUnitPrice() <=> $b->getUnitPrice();
        });

        return $priceCollection->first();
    }

    protected function useAdvancedPricing(): bool
    {
        return $this->pluginConfig->getAdvancedPricing() !== AdvancedPricing::OFF;
    }

    protected function getShopwareProduct(string $productId): ProductEntity
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices.rule.conditions');

        return $this->salesChannelProductRepository->search(
            $criteria,
            $this->salesChannelContext,
        )->first();
    }
}
