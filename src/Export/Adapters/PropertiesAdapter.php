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
    /** @var array  */
    private $properties = [];

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
        if ($product->getTax()) {
            $value = (string)$product->getTax()->getTaxRate();
            $this->addProperty('tax', $value);
        }

        if ($product->getDeliveryDate()->getLatest()) {
            $value = $product->getDeliveryDate()->getLatest()->format(DATE_ATOM);
            $this->addProperty('latestdeliverydate', $value);
        }

        if ($product->getDeliveryDate()->getEarliest()) {
            $value = $product->getDeliveryDate()->getEarliest()->format(DATE_ATOM);
            $this->addProperty('earliestdeliverydate', $value);
        }

        if ($product->getPurchaseUnit()) {
            $value = (string)$product->getPurchaseUnit();
            $this->addProperty('purchaseunit', $value);
        }

        if ($product->getReferenceUnit()) {
            $value = (string)$product->getReferenceUnit();
            $this->addProperty('referenceunit', $value);
        }

        if ($product->getPackUnit()) {
            $value = (string)$product->getPackUnit();
            $this->addProperty('packunit', $value);
        }

        if ($product->getStock()) {
            $value = (string)$product->getStock();
            $this->addProperty('stock', $value);
        }

        if ($product->getAvailableStock()) {
            $value = (string)$product->getAvailableStock();
            $this->addProperty('availableStock', $value);
        }

        if ($product->getWeight()) {
            $value = (string)$product->getWeight();
            $this->addProperty('weight', $value);
        }

        if ($product->getWidth()) {
            $value = (string)$product->getWidth();
            $this->addProperty('width', $value);
        }

        if ($product->getHeight()) {
            $value = (string)$product->getHeight();
            $this->addProperty('height', $value);
        }

        if ($product->getLength()) {
            $value = (string)$product->getLength();
            $this->addProperty('length', $value);
        }

        if ($product->getReleaseDate()) {
            $value = $product->getReleaseDate()->format(DATE_ATOM);
            $this->addProperty('releasedate', $value);
        }

        if ($product->getManufacturer() && $product->getManufacturer()->getMedia()) {
            $value = $product->getManufacturer()->getMedia()->getUrl();
            $this->addProperty('vendorlogo', $value);
        }

        if ($product->getPrice()) {
            /** @var ProductPrice $price */
            $price = $product->getPrice()->getCurrencyPrice($this->salesChannelContext->getCurrency()->getId());
            if ($price) {
                /** @var ProductPrice $listPrice */
                $listPrice = $price->getListPrice();
                if ($listPrice) {
                    $this->addProperty('old_price', (string)$listPrice->getGross());
                    $this->addProperty('old_price_net', (string)$listPrice->getNet());
                }
            }
        }

        if (method_exists($product, 'getMarkAsTopseller')) {
            $isMarkedAsTopseller = $product->getMarkAsTopseller() ?? false;
            $translated = $this->translateBooleanValue($isMarkedAsTopseller);
            $this->addProperty('product_promotion', $translated);
        }

        return $this->properties;
    }

    protected function addProperty(string $name, $value): void
    {
        if (Utils::isEmpty($value)) {
            return;
        }

        $property = new Property($name);
        $property->addValue($value);
        $this->properties[] = $property;
    }

    protected function translateBooleanValue(bool $value)
    {
        $translationKey = $value ? 'finSearch.general.yes' : 'finSearch.general.no';

        return $this->translator->trans($translationKey);
    }
}
