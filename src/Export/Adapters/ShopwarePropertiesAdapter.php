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
    protected Config $config;

    protected SalesChannelContext $salesChannelContext;

    protected TranslatorInterface $translator;

    public function __construct(
        Config $config,
        SalesChannelContext $salesChannelContext,
        TranslatorInterface $translator
    ) {
        $this->config = $config;
        $this->salesChannelContext = $salesChannelContext;
        $this->translator = $translator;

        if (!$this->config->isInitialized()) {
            $this->config->initializeBySalesChannel($this->salesChannelContext);
        }
    }

    public function adapt(ProductEntity $product): array
    {
        $properties = [];

        foreach ($product->getProperties() as $propertyGroupOptionEntity) {
            $group = $propertyGroupOptionEntity->getGroup();
            if ($group && !$group->getFilterable()) {
                // Non-filterable properties should be available in the properties field.
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

        return $properties;
    }

    protected function getAttributeKey(?string $key): ?string
    {
        if ($this->config->isIntegrationTypeApi()) {
            return Utils::removeSpecialChars($key);
        }

        return $key;
    }
}
