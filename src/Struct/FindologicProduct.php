<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Data\DateAdded;
use FINDOLOGIC\Export\Data\Image;
use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\Export\Data\Ordernumber;
use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\Export\Data\Usergroup;
use FINDOLOGIC\Export\Helpers\DataHelper;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FindologicProduct extends Struct
{
    /** @var ProductEntity */
    protected $product;

    /** @var RouterInterface */
    protected $router;

    /** @var ContainerInterface */
    protected $container;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var string */
    protected $shopkey;

    /** @var CustomerGroupEntity[] */
    protected $customerGroups;

    /** @var string */
    protected $name;

    /** @var Attribute[] */
    protected $attributes;

    /** @var Price[] */
    protected $prices;

    /** @var string */
    protected $description;

    /** @var DateAdded|null */
    protected $dateAdded;

    /** @var string */
    protected $url;

    /** @var Keyword[] */
    protected $keywords;

    /** @var Image[] */
    protected $images;

    /** @var int */
    protected $salesFrequency = 0;

    /** @var Usergroup[] */
    protected $userGroups;

    /** @var Ordernumber[] */
    protected $ordernumbers;

    /** @var Property[] */
    protected $properties;

    /** @var Attribute[] */
    protected $customFields = [];

    /** @var Item */
    protected $item;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var DynamicProductGroupService|null
     */
    protected $dynamicProductGroupService;

    /** @var CategoryEntity */
    protected $navigationCategory;

    /**
     * @param CustomerGroupEntity[] $customerGroups
     *
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function __construct(
        ProductEntity $product,
        RouterInterface $router,
        ContainerInterface $container,
        string $shopkey,
        array $customerGroups,
        Item $item
    ) {
        $this->product = $product;
        $this->router = $router;
        $this->container = $container;
        $this->shopkey = $shopkey;
        $this->customerGroups = $customerGroups;
        $this->item = $item;
        $this->prices = [];
        $this->attributes = [];
        $this->properties = [];
        $this->translator = $container->get('translator');

        $this->salesChannelContext = $this->container->get('fin_search.sales_channel_context');
        if ($this->container->has('fin_search.dynamic_product_group')) {
            $this->dynamicProductGroupService = $this->container->get('fin_search.dynamic_product_group');
        }
        $this->navigationCategory = Utils::fetchNavigationCategoryFromSalesChannel(
            $this->container->get('category.repository'),
            $this->salesChannelContext->getSalesChannel()
        );

        $this->setName();
        $this->setAttributes();
        $this->setPrices();
        $this->setDescription();
        $this->setDateAdded();
        $this->setUrl();
        $this->setKeywords();
        $this->setImages();
        $this->setSalesFrequency();
        $this->setUserGroups();
        $this->setOrdernumbers();
        $this->setProperties();
    }

    public function hasName(): bool
    {
        return !Utils::isEmpty($this->name);
    }

    public function hasAttributes(): bool
    {
        return !Utils::isEmpty($this->attributes);
    }

    public function hasPrices(): bool
    {
        return !Utils::isEmpty($this->prices);
    }

    public function hasDescription(): bool
    {
        return !Utils::isEmpty($this->description);
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    public function getName(): string
    {
        if (!$this->hasName()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->name;
    }

    /**
     * @throws ProductHasNoNameException
     */
    protected function setName(): void
    {
        $name = $this->product->getTranslation('name');
        if (Utils::isEmpty($name)) {
            throw new ProductHasNoNameException($this->product);
        }

        $this->name = Utils::removeControlCharacters($name);
    }

    /**
     * @return Attribute[]
     * @throws AccessEmptyPropertyException
     */
    public function getAttributes(): array
    {
        if (!$this->hasAttributes()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->attributes;
    }

    /**
     * @throws ProductHasNoCategoriesException
     */
    protected function setAttributes(): void
    {
        $this->setCategoriesAndCatUrls();
        $this->setVendors();
        $this->setAttributeProperties();
        $this->setCustomFieldAttributes();
        $this->setAdditionalAttributes();
    }

    /**
     * @return Price[]
     * @throws AccessEmptyPropertyException
     */
    public function getPrices(): array
    {
        if (!$this->hasPrices()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->prices;
    }

    /**
     * @throws ProductHasNoPricesException
     */
    protected function setPrices(): void
    {
        $this->setVariantPrices();
        $this->setProductPrices();
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    public function getDescription(): string
    {
        if (!$this->hasDescription()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->description;
    }

    protected function setDescription(): void
    {
        $description = $this->product->getTranslation('description');
        if (!Utils::isEmpty($description)) {
            $this->description = Utils::cleanString($description);
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    public function getDateAdded(): DateAdded
    {
        if (!$this->hasDateAdded()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->dateAdded;
    }

    protected function setDateAdded(): void
    {
        $createdAt = $this->product->getCreatedAt();
        if ($createdAt !== null) {
            $dateAdded = new DateAdded();
            $dateAdded->setDateValue($createdAt);
            $this->dateAdded = $dateAdded;
        }
    }

    public function hasDateAdded(): bool
    {
        return $this->dateAdded && !empty($this->dateAdded);
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    public function getUrl(): string
    {
        if (!$this->hasUrl()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->url;
    }

    protected function setUrl(): void
    {
        $baseUrl = $this->getTranslatedDomainBaseUrl();
        $seoPath = $this->getTranslatedSeoPath();

        if ($baseUrl && $seoPath) {
            $productUrl = sprintf('%s/%s', $baseUrl, $seoPath);
        } else {
            $productUrl = $this->router->generate(
                'frontend.detail.page',
                ['productId' => $this->product->getId()],
                RouterInterface::ABSOLUTE_URL
            );
        }

        $this->url = $productUrl;
    }

    protected function getTranslatedSeoPath(): ?string
    {
        $salesChannel = $this->salesChannelContext->getSalesChannel();
        $seoUrlCollection = $this->product->getSeoUrls()->filterBySalesChannelId($salesChannel->getId());

        /** @var SeoUrlEntity|null $seoUrlEntity */
        $seoUrlEntity = $this->getTranslatedEntity($seoUrlCollection);

        return $seoUrlEntity ? ltrim($seoUrlEntity->getSeoPathInfo(), '/') : null;
    }

    protected function getTranslatedDomainBaseUrl(): ?string
    {
        $salesChannel = $this->salesChannelContext->getSalesChannel();
        $domainCollection = $salesChannel->getDomains();

        /** @var SalesChannelDomainEntity|null $domainEntity */
        $domainEntity = $this->getTranslatedEntity($domainCollection);

        return $domainEntity ? rtrim($domainEntity->getUrl(), '/') : null;
    }

    /**
     * Finds the first entity of a collection for the export language and returns it. If none is found,
     * null is returned.
     */
    protected function getTranslatedEntity(?EntityCollection $collection): ?Entity
    {
        if (!$collection) {
            return null;
        }

        $translatedEntities = $collection->filterByProperty(
            'languageId',
            $this->salesChannelContext->getSalesChannel()->getLanguageId()
        );

        if ($translatedEntities->count() === 0) {
            return null;
        }

        return $translatedEntities->first();
    }

    public function hasUrl(): bool
    {
        return $this->url && !Utils::isEmpty($this->url);
    }

    /**
     * @return Keyword[]
     * @throws AccessEmptyPropertyException
     */
    public function getKeywords(): array
    {
        if (!$this->hasKeywords()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->keywords;
    }

    protected function setKeywords(): void
    {
        $tags = $this->product->getTags();
        if ($tags !== null && $tags->count() > 0) {
            foreach ($tags as $tag) {
                if (!Utils::isEmpty($tag->getName())) {
                    $this->keywords[] = new Keyword($tag->getName());
                }
            }
        }
    }

    public function hasKeywords(): bool
    {
        return $this->keywords && !empty($this->keywords);
    }

    /**
     * @return Image[]
     * @throws AccessEmptyPropertyException
     */
    public function getImages(): array
    {
        if (!$this->hasImages()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->images;
    }

    protected function setImages(): void
    {
        if (!$this->product->getMedia() || !$this->product->getMedia()->count()) {
            $fallbackImage = $this->buildFallbackImage($this->router->getContext());

            if (!Utils::isEmpty($fallbackImage)) {
                $this->images[] = new Image($fallbackImage);
                $this->images[] = new Image($fallbackImage, Image::TYPE_THUMBNAIL);
            }

            return;
        }

        foreach ($this->getSortedImages() as $mediaEntity) {
            if (!$mediaEntity->getMedia() || !$mediaEntity->getMedia()->getUrl()) {
                continue;
            }

            $encodedUrl = $this->getEncodedUrl($mediaEntity->getMedia()->getUrl());
            if (!Utils::isEmpty($encodedUrl)) {
                $this->images[] = new Image($encodedUrl);
            }

            $thumbnails = $mediaEntity->getMedia()->getThumbnails();
            if (!$thumbnails) {
                continue;
            }

            foreach ($thumbnails as $thumbnailEntity) {
                $encodedThumbnailUrl = $this->getEncodedUrl($thumbnailEntity->getUrl());
                if (!Utils::isEmpty($encodedThumbnailUrl)) {
                    $this->images[] = new Image($encodedThumbnailUrl, Image::TYPE_THUMBNAIL);
                }
            }
        }
    }

    public function hasImages(): bool
    {
        return $this->images && !empty($this->images);
    }

    public function getSalesFrequency(): int
    {
        return $this->salesFrequency;
    }

    public function hasSalesFrequency(): bool
    {
        // In case a product has no sales, it's sales frequency would still be 0.
        return true;
    }

    protected function setSalesFrequency(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('payload.productNumber', $this->product->getProductNumber()));

        /** @var EntityRepository $orderLineItemRepository */
        $orderLineItemRepository = $this->container->get('order_line_item.repository');
        $orders = $orderLineItemRepository->search($criteria, $this->salesChannelContext->getContext());

        $this->salesFrequency = $orders->count();
    }

    /**
     * @return Usergroup[]
     * @throws AccessEmptyPropertyException
     */
    public function getUserGroups(): array
    {
        if (!$this->hasUserGroups()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->userGroups;
    }

    protected function setUserGroups(): void
    {
        foreach ($this->customerGroups as $customerGroupEntity) {
            $this->userGroups[] = new Usergroup(
                Utils::calculateUserGroupHash($this->shopkey, $customerGroupEntity->getId())
            );
        }
    }

    public function hasUserGroups(): bool
    {
        return $this->userGroups && !empty($this->userGroups);
    }

    /**
     * @return Ordernumber[]
     * @throws AccessEmptyPropertyException
     */
    public function getOrdernumbers(): array
    {
        if (!$this->hasOrdernumbers()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->ordernumbers;
    }

    protected function setOrdernumbers(): void
    {
        $this->setOrdernumberByProduct($this->product);
        foreach ($this->product->getChildren() as $productEntity) {
            $this->setOrdernumberByProduct($productEntity);
        }
    }

    public function hasOrdernumbers(): bool
    {
        return $this->ordernumbers && !empty($this->ordernumbers);
    }

    /**
     * @return Property[]
     * @throws AccessEmptyPropertyException
     */
    public function getProperties(): array
    {
        if (!$this->hasProperties()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->properties;
    }

    protected function setProperties(): void
    {
        if ($this->product->getTax()) {
            $value = (string)$this->product->getTax()->getTaxRate();
            $this->addProperty('tax', $value);
        }

        if ($this->product->getDeliveryDate()->getLatest()) {
            $value = $this->product->getDeliveryDate()->getLatest()->format(DATE_ATOM);
            $this->addProperty('latestdeliverydate', $value);
        }

        if ($this->product->getDeliveryDate()->getEarliest()) {
            $value = $this->product->getDeliveryDate()->getEarliest()->format(DATE_ATOM);
            $this->addProperty('earliestdeliverydate', $value);
        }

        if ($this->product->getPurchaseUnit()) {
            $value = (string)$this->product->getPurchaseUnit();
            $this->addProperty('purchaseunit', $value);
        }

        if ($this->product->getReferenceUnit()) {
            $value = (string)$this->product->getReferenceUnit();
            $this->addProperty('referenceunit', $value);
        }

        if ($this->product->getPackUnit()) {
            $value = (string)$this->product->getPackUnit();
            $this->addProperty('packunit', $value);
        }

        if ($this->product->getStock()) {
            $value = (string)$this->product->getStock();
            $this->addProperty('stock', $value);
        }

        if ($this->product->getAvailableStock()) {
            $value = (string)$this->product->getAvailableStock();
            $this->addProperty('availableStock', $value);
        }

        if ($this->product->getWeight()) {
            $value = (string)$this->product->getWeight();
            $this->addProperty('weight', $value);
        }

        if ($this->product->getWidth()) {
            $value = (string)$this->product->getWidth();
            $this->addProperty('width', $value);
        }

        if ($this->product->getHeight()) {
            $value = (string)$this->product->getHeight();
            $this->addProperty('height', $value);
        }

        if ($this->product->getLength()) {
            $value = (string)$this->product->getLength();
            $this->addProperty('length', $value);
        }

        if ($this->product->getReleaseDate()) {
            $value = (string)$this->product->getReleaseDate()->format(DATE_ATOM);
            $this->addProperty('releasedate', $value);
        }

        if ($this->product->getManufacturer() && $this->product->getManufacturer()->getMedia()) {
            $value = $this->product->getManufacturer()->getMedia()->getUrl();
            $this->addProperty('vendorlogo', $value);
        }
    }

    public function hasProperties(): bool
    {
        return $this->properties && !empty($this->properties);
    }

    protected function setVendors(): void
    {
        $manufacturer = $this->product->getManufacturer();
        if ($manufacturer) {
            $name = $manufacturer->getTranslation('name');
            if (!Utils::isEmpty($name)) {
                $vendorAttribute = new Attribute('vendor', [Utils::removeControlCharacters($name)]);
                $this->attributes[] = $vendorAttribute;
            }
        }
    }

    /**
     * @return Attribute[]
     */
    protected function getAttributeProperties(ProductEntity $productEntity): array
    {
        $attributes = [];

        foreach ($productEntity->getProperties() as $propertyGroupOptionEntity) {
            $group = $propertyGroupOptionEntity->getGroup();
            // Method getFilterable exists since Shopware 6.2.x.
            if ($group && method_exists($group, 'getFilterable') && !$group->getFilterable()) {
                // Non filterable properties should be available in the properties field.
                $this->properties = array_merge(
                    $this->properties,
                    $this->getAttributePropertyAsProperty($propertyGroupOptionEntity)
                );

                continue;
            }

            $attributes = array_merge($attributes, $this->getAttributePropertyAsAttribute($propertyGroupOptionEntity));
        }

        return $attributes;
    }

    /**
     * @return Property[]
     */
    protected function getAttributePropertyAsProperty(PropertyGroupOptionEntity $propertyGroupOptionEntity): array
    {
        $properties = [];

        $group = $propertyGroupOptionEntity->getGroup();
        if ($group && $propertyGroupOptionEntity->getTranslation('name') && $group->getTranslation('name')) {
            $groupName = Utils::removeSpecialChars($group->getTranslation('name'));
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

            $groupName = $group->getTranslation('name');
            $optionName = $settingOption->getTranslation('name');
            if (!Utils::isEmpty($groupName) && !Utils::isEmpty($optionName)) {
                $configProperty = new Property(Utils::removeSpecialChars($groupName));
                $configProperty->addValue(Utils::removeControlCharacters($optionName));

                $properties[] = $configProperty;
            }
        }

        return $properties;
    }

    /**
     * @return Attribute[]
     */
    protected function getAttributePropertyAsAttribute(PropertyGroupOptionEntity $propertyGroupOptionEntity): array
    {
        $attributes = [];

        $group = $propertyGroupOptionEntity->getGroup();
        if ($group && $propertyGroupOptionEntity->getTranslation('name') && $group->getTranslation('name')) {
            $groupName = Utils::removeSpecialChars($group->getTranslation('name'));
            $propertyGroupOptionName = $propertyGroupOptionEntity->getTranslation('name');
            if (!Utils::isEmpty($groupName) && !Utils::isEmpty($propertyGroupOptionName)) {
                $properyGroupAttrib = new Attribute(Utils::removeSpecialChars($groupName));
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

            $groupName = $group->getTranslation('name');
            $optionName = $settingOption->getTranslation('name');
            if (!Utils::isEmpty($groupName) && !Utils::isEmpty($optionName)) {
                $configAttrib = new Attribute(Utils::removeSpecialChars($groupName));
                $configAttrib->addValue(Utils::removeControlCharacters($optionName));

                $attributes[] = $configAttrib;
            }
        }

        return $attributes;
    }

    protected function setAttributeProperties(): void
    {
        $this->attributes = array_merge($this->attributes, $this->getAttributeProperties($this->product));
        foreach ($this->product->getChildren() as $productEntity) {
            $this->attributes = array_merge($this->attributes, $this->getAttributeProperties($productEntity));
        }
    }

    protected function setAdditionalAttributes(): void
    {
        $shippingFree = $this->translateBooleanValue($this->product->getShippingFree());
        $this->attributes[] = new Attribute('shipping_free', [$shippingFree]);
        $rating = $this->product->getRatingAverage() ?? 0.0;
        $this->attributes[] = new Attribute('rating', [$rating]);

        // Add custom fields in the attributes array for export
        $this->attributes = array_merge($this->attributes, $this->customFields);
    }

    protected function setOrdernumberByProduct(ProductEntity $product): void
    {
        if (!Utils::isEmpty($product->getProductNumber())) {
            $this->ordernumbers[] = new Ordernumber($product->getProductNumber());
        }
        if (!Utils::isEmpty($product->getEan())) {
            $this->ordernumbers[] = new Ordernumber($product->getEan());
        }
        if (!Utils::isEmpty($product->getManufacturerNumber())) {
            $this->ordernumbers[] = new Ordernumber($product->getManufacturerNumber());
        }
    }

    /**
     * @throws ProductHasNoCategoriesException
     */
    protected function setCategoriesAndCatUrls(): void
    {
        $productCategories = $this->product->getCategories();
        if ($productCategories === null || empty($productCategories->count())) {
            throw new ProductHasNoCategoriesException($this->product);
        }

        $categoryAttribute = new Attribute('cat');
        $catUrlAttribute = new Attribute('cat_url');

        $catUrls = [];
        $categories = [];

        $this->parseCategoryAttributes($productCategories->getElements(), $catUrls, $categories);
        if ($this->dynamicProductGroupService) {
            $dynamicGroupCategories = $this->dynamicProductGroupService->getCategories($this->product->getId());
            $this->parseCategoryAttributes($dynamicGroupCategories, $catUrls, $categories);
        }

        if (!Utils::isEmpty($catUrls)) {
            $catUrlAttribute->setValues(array_unique($catUrls));
            $this->attributes[] = $catUrlAttribute;
        }

        if (!Utils::isEmpty($categories)) {
            $categoryAttribute->setValues(array_unique($categories));
            $this->attributes[] = $categoryAttribute;
        }
    }

    protected function setVariantPrices(): void
    {
        if ($this->product->getChildCount() === 0) {
            return;
        }

        foreach ($this->product->getChildren() as $variant) {
            if (!$variant->getActive() || $variant->getStock() <= 0) {
                continue;
            }

            $this->prices = array_merge($this->prices, $this->getPricesFromProduct($variant));
        }
    }

    /**
     * @return Price[]
     */
    protected function getPricesFromProduct(ProductEntity $variant): array
    {
        $prices = [];

        foreach ($variant->getPrice() as $item) {
            foreach ($this->customerGroups as $customerGroup) {
                $userGroupHash = Utils::calculateUserGroupHash($this->shopkey, $customerGroup->getId());
                if (Utils::isEmpty($userGroupHash)) {
                    continue;
                }

                $price = new Price();
                if ($customerGroup->getDisplayGross()) {
                    $price->setValue($item->getGross(), $userGroupHash);
                } else {
                    $price->setValue($item->getNet(), $userGroupHash);
                }

                $prices[] = $price;
            }

            $price = new Price();
            $price->setValue($item->getGross());
            $prices[] = $price;
        }

        return $prices;
    }

    /**
     * @throws ProductHasNoPricesException
     */
    protected function setProductPrices(): void
    {
        $prices = $this->getPricesFromProduct($this->product);
        if (Utils::isEmpty($prices)) {
            throw new ProductHasNoPricesException($this->product);
        }

        $this->prices = array_merge($this->prices, $prices);
    }

    /**
     * Takes invalid URLs that contain special characters such as umlauts, or special UTF-8 characters and
     * encodes them.
     */
    protected function getEncodedUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        $urlPath = explode('/', $parsedUrl['path']);
        $encodedPath = array_map('\FINDOLOGIC\FinSearch\Utils\Utils::multiByteRawUrlEncode', $urlPath);
        $parsedUrl['path'] = implode('/', $encodedPath);

        return Utils::buildUrl($parsedUrl);
    }

    protected function buildFallbackImage(RequestContext $requestContext): string
    {
        $schemaAuthority = $requestContext->getScheme() . '://' . $requestContext->getHost();
        if ($requestContext->getHttpPort() !== 80) {
            $schemaAuthority .= ':' . $requestContext->getHttpPort();
        } elseif ($requestContext->getHttpsPort() !== 443) {
            $schemaAuthority .= ':' . $requestContext->getHttpsPort();
        }

        return sprintf(
            '%s/%s',
            $schemaAuthority,
            'bundles/storefront/assets/icon/default/placeholder.svg'
        );
    }

    protected function fetchCategorySeoUrls(CategoryEntity $categoryEntity): SeoUrlCollection
    {
        $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();
        $seoUrls = new SeoUrlCollection();

        foreach ($categoryEntity->getSeoUrls()->getElements() as $seoUrlEntity) {
            if ($seoUrlEntity->getSalesChannelId() === $salesChannelId || $seoUrlEntity->getSalesChannelId() === null) {
                $seoUrls->add($seoUrlEntity);
            }
        }

        return $seoUrls;
    }

    protected function getSortedImages(): ProductMediaCollection
    {
        $images = $this->product->getMedia();
        $coverImageId = $this->product->getCoverId();
        $coverImage = $images->get($coverImageId);

        if (!$coverImage || $images->count() === 1) {
            return $images;
        }

        $images->remove($coverImageId);
        $images->insert(0, $coverImage);

        return $images;
    }

    protected function setCustomFieldAttributes(): void
    {
        $this->customFields = array_merge($this->customFields, $this->getCustomFieldProperties($this->product));
        if ($this->product->getChildCount() === 0) {
            return;
        }
        foreach ($this->product->getChildren() as $productEntity) {
            $this->customFields = array_merge($this->customFields, $this->getCustomFieldProperties($productEntity));
        }
    }

    protected function getCustomFieldProperties(ProductEntity $product): array
    {
        $attributes = [];

        $productFields = $product->getCustomFields();
        if (empty($productFields)) {
            return [];
        }

        foreach ($productFields as $key => $value) {
            $cleanedKey = Utils::removeSpecialChars($key);
            $cleanedValue = $this->getCleanedAttributeValue($value);

            if (!Utils::isEmpty($cleanedKey) && !Utils::isEmpty($cleanedValue)) {
                $customFieldAttribute = new Attribute($cleanedKey, (array)$cleanedValue);
                $attributes[] = $customFieldAttribute;
            }
        }

        return $attributes;
    }

    /**
     * @return Attribute[]
     */
    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    /**
     * @param array<string, int, bool>|string|int|bool $value
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

    protected function addProperty(string $name, $value): void
    {
        if (!Utils::isEmpty($value)) {
            $property = new Property($name);
            $property->addValue($value);
            $this->properties[] = $property;
        }
    }

    private function parseCategoryAttributes(
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

            if (!$categoryEntity->getPath() || !strpos($categoryEntity->getPath(), $navigationCategoryId)) {
                continue;
            }

            $seoUrls = $this->fetchCategorySeoUrls($categoryEntity);
            if ($seoUrls->count() > 0) {
                foreach ($seoUrls->getElements() as $seoUrlEntity) {
                    $catUrl = $seoUrlEntity->getSeoPathInfo();
                    if (!Utils::isEmpty($catUrl)) {
                        $catUrls[] = sprintf('/%s', ltrim($catUrl, '/'));
                    }
                }
            }

            $catUrl = sprintf(
                '/%s',
                ltrim(
                    $this->router->generate(
                        'frontend.navigation.page',
                        ['navigationId' => $categoryEntity->getId()],
                        RouterInterface::ABSOLUTE_PATH
                    ),
                    '/'
                )
            );

            if (!Utils::isEmpty($catUrl)) {
                $catUrls[] = $catUrl;
            }

            $categoryPath = Utils::buildCategoryPath(
                $categoryEntity->getBreadcrumb(),
                $this->navigationCategory
            );

            if (!Utils::isEmpty($categoryPath)) {
                $categories[] = $categoryPath;
            }
        }
    }
}
