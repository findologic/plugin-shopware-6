<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\Export\Data\Property;
use Shopware\Core\Content\Product\ProductEntity;

trait PropertiesHelper
{
    /**
     * @return Property[]
     */
    public function getProperties(ProductEntity $productEntity): array
    {
        $properties = [];

        if ($productEntity->getTax()) {
            $property = new Property('tax');
            $property->addValue((string)$productEntity->getTax()->getTaxRate());
            $properties[] = $property;
        }

        if ($productEntity->getDeliveryDate()->getLatest()) {
            $property = new Property('latestdeliverydate');
            $property->addValue($productEntity->getDeliveryDate()->getLatest()->format(DATE_ATOM));
            $properties[] = $property;
        }

        if ($productEntity->getDeliveryDate()->getEarliest()) {
            $property = new Property('earliestdeliverydate');
            $property->addValue($productEntity->getDeliveryDate()->getEarliest()->format(DATE_ATOM));
            $properties[] = $property;
        }

        if ($productEntity->getPurchaseUnit()) {
            $property = new Property('purchaseunit');
            $property->addValue((string)$productEntity->getPurchaseUnit());
            $properties[] = $property;
        }

        if ($productEntity->getReferenceUnit()) {
            $property = new Property('referenceunit');
            $property->addValue((string)$productEntity->getReferenceUnit());
            $properties[] = $property;
        }

        if ($productEntity->getPackUnit()) {
            $property = new Property('packunit');
            $property->addValue((string)$productEntity->getPackUnit());
            $properties[] = $property;
        }

        if ($productEntity->getStock()) {
            $property = new Property('stock');
            $property->addValue((string)$productEntity->getStock());
            $properties[] = $property;
        }

        if ($productEntity->getAvailableStock()) {
            $property = new Property('availableStock');
            $property->addValue((string)$productEntity->getAvailableStock());
            $properties[] = $property;
        }

        if ($productEntity->getWeight()) {
            $property = new Property('weight');
            $property->addValue((string)$productEntity->getWeight());
            $properties[] = $property;
        }

        if ($productEntity->getWidth()) {
            $property = new Property('width');
            $property->addValue((string)$productEntity->getWidth());
            $properties[] = $property;
        }

        if ($productEntity->getHeight()) {
            $property = new Property('height');
            $property->addValue((string)$productEntity->getHeight());
            $properties[] = $property;
        }

        if ($productEntity->getLength()) {
            $property = new Property('length');
            $property->addValue((string)$productEntity->getLength());
            $properties[] = $property;
        }

        if ($productEntity->getReleaseDate()) {
            $property = new Property('releasedate');
            $property->addValue((string)$productEntity->getReleaseDate()->format(DATE_ATOM));
            $properties[] = $property;
        }

        if ($productEntity->getManufacturer() && $productEntity->getManufacturer()->getMedia()) {
            $property = new Property('vendorlogo');
            $property->addValue($productEntity->getManufacturer()->getMedia()->getUrl());
            $properties[] = $property;
        }

        if ($productEntity->getPrice()) {
            $price = $productEntity->getPrice()->getCurrencyPrice($this->salesChannelContext->getCurrency()->getId());
            if ($price) {
                $listPrice = $price->getListPrice();
                if ($listPrice) {
                    $property = new Property('old_price');
                    $property->addValue((string)$listPrice->getGross());
                    $properties[] = $property;

                    $property = new Property('old_price_net');
                    $property->addValue((string)$listPrice->getNet());
                    $properties[] = $property;
                }
            }
        }

        if (method_exists($productEntity, 'getMarkAsTopseller')) {
            $isMarkedAsTopseller = $productEntity->getMarkAsTopseller() ?? false;
            $promotionValue = $this->translateBooleanValue($isMarkedAsTopseller);
            $property = new Property('product_promotion');
            $property->addValue($promotionValue);
            $properties[] = $property;
        }

        return $properties;
    }

    private function translateBooleanValue(bool $value): string
    {
        $translationKey = $value ? 'finSearch.general.yes' : 'finSearch.general.no';

        return $this->getContainer()->get('translator')->trans($translationKey);
    }
}
