<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as ProductPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class PropertiesAdapter
{
    /** @var SalesChannelContext $salesChannelContext */
    private $salesChannelContext;

    /** @var TranslatorInterface $translator */
    private $translator;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        TranslatorInterface $translator
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->translator = $translator;
    }

    public function adapt(ProductEntity $product): array
    {
        $properties = [];

        if ($product->getTax()) {
            $value = (string)$product->getTax()->getTaxRate();
            $properties[] = $this->getProperty('tax', $value);
        }

        if ($product->getDeliveryDate()->getLatest()) {
            $value = $product->getDeliveryDate()->getLatest()->format(DATE_ATOM);
            $properties[] = $this->getProperty('latestdeliverydate', $value);
        }

        if ($product->getDeliveryDate()->getEarliest()) {
            $value = $product->getDeliveryDate()->getEarliest()->format(DATE_ATOM);
            $properties[] = $this->getProperty('earliestdeliverydate', $value);
        }

        if ($product->getPurchaseUnit()) {
            $value = (string)$product->getPurchaseUnit();
            $properties[] = $this->getProperty('purchaseunit', $value);
        }

        if ($product->getReferenceUnit()) {
            $value = (string)$product->getReferenceUnit();
            $properties[] = $this->getProperty('referenceunit', $value);
        }

        if ($product->getPackUnit()) {
            $value = (string)$product->getPackUnit();
            $properties[] = $this->getProperty('packunit', $value);
        }

        if ($product->getStock()) {
            $value = (string)$product->getStock();
            $properties[] = $this->getProperty('stock', $value);
        }

        if ($product->getAvailableStock()) {
            $value = (string)$product->getAvailableStock();
            $properties[] = $this->getProperty('availableStock', $value);
        }

        if ($product->getWeight()) {
            $value = (string)$product->getWeight();
            $properties[] = $this->getProperty('weight', $value);
        }

        if ($product->getWidth()) {
            $value = (string)$product->getWidth();
            $properties[] = $this->getProperty('width', $value);
        }

        if ($product->getHeight()) {
            $value = (string)$product->getHeight();
            $properties[] = $this->getProperty('height', $value);
        }

        if ($product->getLength()) {
            $value = (string)$product->getLength();
            $properties[] =  $this->getProperty('length', $value);
        }

        if ($product->getReleaseDate()) {
            $value = $product->getReleaseDate()->format(DATE_ATOM);
            $properties[] = $this->getProperty('releasedate', $value);
        }

        if ($product->getManufacturer() && $product->getManufacturer()->getMedia()) {
            $value = $product->getManufacturer()->getMedia()->getUrl();
            $properties[] = $this->getProperty('vendorlogo', $value);
        }

        if ($product->getPrice()) {
            /** @var ProductPrice $price */
            $price = $product->getPrice()->getCurrencyPrice($this->salesChannelContext->getCurrency()->getId());
            if ($price) {
                /** @var ProductPrice $listPrice */
                $listPrice = $price->getListPrice();
                if ($listPrice) {
                    $properties[] = $this->getProperty('old_price', (string)$listPrice->getGross());
                    $properties[] = $this->getProperty('old_price_net', (string)$listPrice->getNet());
                }
            }
        }

        if (method_exists($product, 'getMarkAsTopseller')) {
            $isMarkedAsTopseller = $product->getMarkAsTopseller() ?? false;
            $translated = $this->translateBooleanValue($isMarkedAsTopseller);
            $properties[] = $this->getProperty('product_promotion', $translated);
        }

        return $properties;
    }

    protected function getProperty(string $name, $value): ?Property
    {
        if (Utils::isEmpty($value)) {
            return null;
        }

        $property = new Property($name);
        $property->addValue($value);

        return $property;
    }

    protected function translateBooleanValue(bool $value): string
    {
        $translationKey = $value ? 'finSearch.general.yes' : 'finSearch.general.no';

        return $this->translator->trans($translationKey);
    }
}
