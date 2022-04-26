<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as ProductPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class PropertiesAdapter
{
    /** @var SalesChannelContext $salesChannelContext */
    protected $salesChannelContext;

    /** @var TranslatorInterface $translator */
    protected $translator;

    /** @var Config $config */
    protected $config;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        TranslatorInterface $translator,
        Config $config
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->translator = $translator;
        $this->config = $config;
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
            $properties[] = $this->getProperty('length', $value);
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

        foreach ($product->getProperties() as $propertyGroupOptionEntity) {
            $group = $propertyGroupOptionEntity->getGroup();
            // Method getFilterable exists since Shopware 6.2.x.
            if ($group && method_exists($group, 'getFilterable') && !$group->getFilterable()) {
                // Non filterable properties should be available in the properties field.
                $properties = array_merge(
                    $properties,
                    $this->getAttributePropertyAsProperty($propertyGroupOptionEntity)
                );
            }
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

    protected function getAttributePropertyAsProperty(PropertyGroupOptionEntity $propertyGroupOptionEntity): array
    {
        $properties = [];

        $group = $propertyGroupOptionEntity->getGroup();
        if ($group && $propertyGroupOptionEntity->getTranslation('name') && $group->getTranslation('name')) {
            $groupName = $this->getAttributeKey($group->getTranslation('name'));
            $propertyGroupOptionName = $propertyGroupOptionEntity->getTranslation('name');
            if (!Utils::isEmpty($groupName) && !Utils::isEmpty($propertyGroupOptionName)) {
                $propertyGroupProperty = new Property($groupName);
                $propertyGroupProperty->addValue(Utils::removeControlCharacters($propertyGroupOptionName));

                $properties[] = $propertyGroupProperty;
            }
        }

        foreach ($propertyGroupOptionEntity->getProductConfiguratorSettings() as $setting) {
            $settingOption = $setting->getOption();
            if ($settingOption) {
                $group = $settingOption->getGroup();
            }

            if (!$group) {
                continue;
            }

            $groupName = $this->getAttributeKey($group->getTranslation('name'));
            $optionName = $settingOption->getTranslation('name');
            if (!Utils::isEmpty($groupName) && !Utils::isEmpty($optionName)) {
                $configProperty = new Property($groupName);
                $configProperty->addValue(Utils::removeControlCharacters($optionName));

                $properties[] = $configProperty;
            }
        }

        return $properties;
    }

    protected function getAttributeKey(?string $key): ?string
    {
        if ($this->isApiIntegration()) {
            return Utils::removeSpecialChars($key);
        }

        return $key;
    }

    protected function isApiIntegration(): bool
    {
        return $this->config->getIntegrationType() === IntegrationType::API;
    }
}
