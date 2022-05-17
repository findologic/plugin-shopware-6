<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PriceAdapter
{
    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var ExportContext */
    protected $exportContext;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        ExportContext $exportContext
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->exportContext = $exportContext;
    }

    /**
     * @return Price[]
     * @throws ProductHasNoPricesException
     */
    public function adapt(ProductEntity $product): array
    {
        $prices = $this->getPricesFromProduct($product);
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
}
