<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Ordernumber;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;

class OrderNumberAdapter
{
    public function adapt(ProductEntity $product): array
    {
        return $this->getOrderNumber($product);
    }

    protected function getOrderNumber(ProductEntity $product): array
    {
        $orderNumbers = [];

        if (!Utils::isEmpty($product->getProductNumber())) {
            $orderNumbers[] = new Ordernumber($product->getProductNumber());
        }

        if (!Utils::isEmpty($product->getEan())) {
            $orderNumbers[] = new Ordernumber($product->getEan());
        }

        if (!Utils::isEmpty($product->getManufacturerNumber())) {
            $orderNumbers[] = new Ordernumber($product->getManufacturerNumber());
        }

        return $orderNumbers;
    }
}
