<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Helpers\DataHelper;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class AttributeAdapter
{
    /** @var ContainerInterface */
    protected $container;

    /** @var Config */
    protected $config;

    /** @var DynamicProductGroupService|null */
    protected $dynamicProductGroupService;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var UrlBuilderService */
    protected $urlBuilderService;

    /** @var ExportContext */
    protected $exportContext;

    public function __construct(
        ContainerInterface $container,
        Config $config,
        TranslatorInterface $translator,
        SalesChannelContext $salesChannelContext,
        UrlBuilderService $urlBuilderService,
        ExportContext $exportContext
    ) {
        $this->container = $container;
        $this->config = $config;
        $this->translator = $translator;
        $this->salesChannelContext = $salesChannelContext;
        $this->urlBuilderService = $urlBuilderService;
        $this->exportContext = $exportContext;

        if (!$this->config->isInitialized()) {
            $this->config->initializeBySalesChannel($this->salesChannelContext);
        }

        if ($this->container->has('fin_search.dynamic_product_group')) {
            $this->dynamicProductGroupService = $this->container->get('fin_search.dynamic_product_group');
        }
    }

    /**
     * @return Attribute[]
     * @throws ProductHasNoCategoriesException
     */
    public function adapt(ProductEntity $product): array
    {
        $categoryAttributes = $this->getCategoryAndCatUrlAttributes($product);
        $manufacturerAttributes = $this->getManufacturerAttributes($product);
        $propertyAttributes = $this->getPropertyAttributes($product);
        $customFieldAttributes = $this->getCustomFieldAttributes($product);
        $additionalAttributes = $this->getAdditionalAttributes($product);

        return array_merge(
            $categoryAttributes,
            $manufacturerAttributes,
            $propertyAttributes,
            $customFieldAttributes,
            $additionalAttributes
        );
    }

    /**
     * @return Attribute[]
     * @throws ProductHasNoCategoriesException
     */
    protected function getCategoryAndCatUrlAttributes(ProductEntity $product): array
    {
        $productCategories = $product->getCategories();
        if ($productCategories === null || empty($productCategories->count())) {
            throw new ProductHasNoCategoriesException($product);
        }

        $catUrls = [];
        $categories = [];

        $this->parseCategoryAttributes($productCategories->getElements(), $catUrls, $categories);
        if ($this->dynamicProductGroupService) {
            $dynamicGroupCategories = $this->dynamicProductGroupService->getCategories($product->getId());
            $this->parseCategoryAttributes($dynamicGroupCategories, $catUrls, $categories);
        }

        $attributes = [];
        if ($this->config->isIntegrationTypeDirectIntegration() && !Utils::isEmpty($catUrls)) {
            $catUrlAttribute = new Attribute('cat_url');
            $catUrlAttribute->setValues($this->decodeHtmlEntities(Utils::flattenWithUnique($catUrls)));
            $attributes[] = $catUrlAttribute;
        }

        if (!Utils::isEmpty($categories)) {
            $categoryAttribute = new Attribute('cat');
            $categoryAttribute->setValues($this->decodeHtmlEntities(array_unique($categories)));
            $attributes[] = $categoryAttribute;
        }

        return $attributes;
    }

    protected function parseCategoryAttributes(
        array $categoryCollection,
        array &$catUrls,
        array &$categories
    ): void {
        if (!$categoryCollection) {
            return;
        }

        $navigationCategoryId = $this->salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        /** @var CategoryEntity $categoryEntity */
        foreach ($categoryCollection as $categoryEntity) {
            if (!$categoryEntity->getActive()) {
                continue;
            }

            // If the category is not in the current sales channel's root category, we do not need to export it.
            if (!$categoryEntity->getPath() || !strpos($categoryEntity->getPath(), $navigationCategoryId)) {
                continue;
            }

            $categoryPath = Utils::buildCategoryPath(
                $categoryEntity->getBreadcrumb(),
                $this->exportContext->getNavigationRootCategory()
            );

            if (!Utils::isEmpty($categoryPath)) {
                $categories = array_merge($categories, [$categoryPath]);
            }

            if (!$this->config->isIntegrationTypeDirectIntegration()) {
                continue;
            }

            // Only export `cat_url`s recursively if integration type is Direct Integration.
            $this->urlBuilderService->setSalesChannelContext($this->salesChannelContext);

            $catUrls = array_merge(
                $catUrls,
                $this->urlBuilderService->getCategoryUrls($categoryEntity, $this->salesChannelContext->getContext())
            );
        }
    }

    protected function getManufacturerAttributes(ProductEntity $product): array
    {
        $attributes = [];
        if (!$manufacturer = $product->getManufacturer()) {
            return $attributes;
        }

        $name = $manufacturer->getTranslation('name');
        if (Utils::isEmpty($name)) {
            return $attributes;
        }

        $attributes[] = new Attribute('vendor', [Utils::removeControlCharacters($name)]);

        return $attributes;
    }

    /**
     * @return Attribute[]
     */
    protected function getPropertyAttributes(ProductEntity $productEntity): array
    {
        $attributes = [];

        foreach ($productEntity->getProperties() as $propertyGroupOptionEntity) {
            $group = $propertyGroupOptionEntity->getGroup();
            if ($group && !$group->getFilterable()) {
                continue;
            }

            $attributes = array_merge($attributes, $this->getAttributePropertyAsAttribute($propertyGroupOptionEntity));
        }

        return $attributes;
    }

    /**
     * @return Attribute[]
     */
    protected function getAttributePropertyAsAttribute(PropertyGroupOptionEntity $propertyGroupOptionEntity): array
    {
        $attributes = [];

        $group = $propertyGroupOptionEntity->getGroup();
        if ($group && $propertyGroupOptionEntity->getTranslation('name') && $group->getTranslation('name')) {
            $groupName = $this->getAttributeKey($group->getTranslation('name'));
            $propertyGroupOptionName = $propertyGroupOptionEntity->getTranslation('name');
            if (!Utils::isEmpty($groupName) && !Utils::isEmpty($propertyGroupOptionName)) {
                $properyGroupAttrib = new Attribute($groupName);
                $properyGroupAttrib->addValue(Utils::removeControlCharacters($propertyGroupOptionName));

                $attributes[] = $properyGroupAttrib;
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
                $configAttrib = new Attribute($groupName);
                $configAttrib->addValue(Utils::removeControlCharacters($optionName));

                $attributes[] = $configAttrib;
            }
        }

        return $attributes;
    }

    protected function getCustomFieldAttributes(ProductEntity $product): array
    {
        $attributes = [];

        $productFields = $product->getCustomFields();
        if (empty($productFields)) {
            return [];
        }

        foreach ($productFields as $key => $value) {
            $key = $this->getAttributeKey($key);
            $cleanedValue = $this->getCleanedAttributeValue($value);

            if (!Utils::isEmpty($key) && !Utils::isEmpty($cleanedValue)) {
                // Third-Party plugins may allow setting multidimensional custom-fields. As those can not really
                // be properly sanitized, they need to be skipped.
                if (is_array($cleanedValue) && is_array(array_values($cleanedValue)[0])) {
                    continue;
                }

                // Filter null, false and empty strings, but not "0". See: https://stackoverflow.com/a/27501297/6281648
                $customFieldAttribute = new Attribute(
                    $key,
                    $this->decodeHtmlEntities(array_filter((array)$cleanedValue, 'strlen'))
                );
                $attributes[] = $customFieldAttribute;
            }
        }

        return $attributes;
    }

    protected function decodeHtmlEntities(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = $this->decodeHtmlEntity($value);
        }

        return $values;
    }

    /**
     * @param mixed $value
     * @return string|mixed
     */
    protected function decodeHtmlEntity($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        return html_entity_decode($value);
    }

    /**
     * @return Attribute[]
     */
    protected function getAdditionalAttributes(ProductEntity $product): array
    {
        $attributes = [];

        $shippingFree = $this->translateBooleanValue($product->getShippingFree());
        $attributes[] = new Attribute('shipping_free', [$shippingFree]);
        $rating = $product->getRatingAverage() ?? 0.0;
        $attributes[] = new Attribute('rating', [$rating]);

        return $attributes;
    }

    /**
     * For API Integrations, we have to remove special characters from the attribute key as a requirement for
     * sending data via API.
     */
    protected function getAttributeKey(?string $key): ?string
    {
        if ($this->config->isIntegrationTypeApi()) {
            return Utils::removeSpecialChars($key);
        }

        return $key;
    }

    /**
     * @param array<string, int, bool>|string|int|bool $value
     *
     * @return array<string, int, bool>|string|int|bool
     */
    protected function getCleanedAttributeValue($value)
    {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $item) {
                $values[] = $this->getCleanedAttributeValue($item);
            }

            return $values;
        }

        if (is_string($value)) {
            if (mb_strlen($value) > DataHelper::ATTRIBUTE_CHARACTER_LIMIT) {
                return '';
            }

            return Utils::cleanString($value);
        }

        if (is_bool($value)) {
            return $this->translateBooleanValue($value);
        }

        return $value;
    }

    protected function translateBooleanValue(bool $value)
    {
        $translationKey = $value ? 'finSearch.general.yes' : 'finSearch.general.no';

        return $this->translator->trans($translationKey);
    }
}
