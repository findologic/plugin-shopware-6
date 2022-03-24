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
        return array_merge($this->getParentItemOrderNumber($product), $this->getChildrenProductsOrderNumbers($product));
    }

    private function getParentItemOrderNumber(ProductEntity $product): array
    {
        $orderNumbers = [];
        $orderNumber = $this->getOrderNumber($product);

        if (null !== $orderNumber) {
            return [];
        }

        $orderNumbers[] = $orderNumber;

        return $orderNumbers;
    }

    private function getChildrenProductsOrderNumbers(ProductEntity $product): array
    {
        $orderNumbers = [];

        foreach ($product->getChildren() as $productEntity) {
            $orderNumber = $this->getOrderNumber($productEntity);
            if (null === $orderNumber) {
                continue;
            }

            $orderNumbers[] = $orderNumber;
        }

        return $orderNumbers;
    }

    private function getOrderNumber(ProductEntity $product): ?Ordernumber
    {
        if (!Utils::isEmpty($product->getProductNumber())) {
            return new Ordernumber($product->getProductNumber());
        }

        if (!Utils::isEmpty($product->getEan())) {
            return new Ordernumber($product->getEan());
        }

        if (!Utils::isEmpty($product->getManufacturerNumber())) {
            return new Ordernumber($product->getManufacturerNumber());
        }

        return null;
    }
}
