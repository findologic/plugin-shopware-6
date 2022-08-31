<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Provider;

use FINDOLOGIC\FinSearch\Findologic\AdvancedPricing;
use FINDOLOGIC\FinSearch\Struct\Config;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PriceBasedOnConfigurationProvider
{
    /** @var Config */
    private $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    public function getPriceBasedOnConfiguration(PriceCollection $priceCollection): ?CalculatedPrice
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
}
