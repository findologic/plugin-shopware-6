<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShopwarePropertiesAdapter
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
