<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use DateTimeImmutable;
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
use FINDOLOGIC\FinSearch\Export\ProductImageService;
use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as ProductPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function method_exists;

/**
 * @deprecated FindologicProduct class will be removed in plugin version 5.0
 */
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

    /** @var TranslatorInterface */
    protected $translator;

    /** @var DynamicProductGroupService|null */
    protected $dynamicProductGroupService;

    /** @var CategoryEntity */
    protected $navigationCategory;

    /** @var ProductImageService */
    protected $productImageService;

    /** @var Config */
    protected $config;

    /** @var UrlBuilderService */
    protected $urlBuilderService;

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
        Item $item,
        ?Config $config = null
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
        $this->config = $config ?? $container->get(Config::class);

        if (!$this->config->isInitialized()) {
            $this->config->initializeBySalesChannel($this->salesChannelContext);
        }
        if ($this->container->has('fin_search.dynamic_product_group')) {
            $this->dynamicProductGroupService = $this->container->get('fin_search.dynamic_product_group');
        }
        $this->navigationCategory = Utils::fetchNavigationCategoryFromSalesChannel(
            $this->container->get('category.repository'),
            $this->salesChannelContext->getSalesChannel()
        );
        $this->urlBuilderService = $this->container->get(UrlBuilderService::class);
        $this->urlBuilderService->setSalesChannelContext($this->salesChannelContext);
        $this->productImageService = $this->container->get(ProductImageService::class);

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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasName(): bool
    {
        return !Utils::isEmpty($this->name);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasAttributes(): bool
    {
        return !Utils::isEmpty($this->attributes);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @throws ProductHasNoPricesException
     */
    protected function setPrices(): void
    {
        $this->setVariantPrices();
        $this->setProductPrices();
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasPrices(): bool
    {
        return !Utils::isEmpty($this->prices);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @throws AccessEmptyPropertyException
     */
    public function getDescription(): string
    {
        if (!$this->hasDescription()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->description;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setDescription(): void
    {
        $description = $this->product->getTranslation('description');
        if (!Utils::isEmpty($description)) {
            $this->description = Utils::cleanString($description);
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @return bool
     */
    public function hasDescription(): bool
    {
        return !Utils::isEmpty($this->description);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @throws AccessEmptyPropertyException
     */
    public function getDateAdded(): DateAdded
    {
        if (!$this->hasDateAdded()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->dateAdded;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     *
     */
    protected function setDateAdded(): void
    {
        $releaseDate = $this->product->getReleaseDate();
        if ($releaseDate !== null) {
            $dateAdded = new DateAdded();
            $dateAdded->setDateValue($releaseDate);
            $this->dateAdded = $dateAdded;
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasDateAdded(): bool
    {
        return $this->dateAdded && !empty($this->dateAdded);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @throws AccessEmptyPropertyException
     */
    public function getUrl(): string
    {
        if (!$this->hasUrl()) {
            throw new AccessEmptyPropertyException($this->product);
        }

        return $this->url;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setUrl(): void
    {
        $this->url = $this->urlBuilderService->buildProductUrl($this->product);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasUrl(): bool
    {
        return $this->url && !Utils::isEmpty($this->url);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setKeywords(): void
    {
        $blackListedKeywords = [
            $this->product->getProductNumber(),
        ];
        $keywords = $this->product->getSearchKeywords();

        if ($manufacturer = $this->product->getManufacturer()) {
            $blackListedKeywords[] = $manufacturer->getTranslation('name');
        }

        if ($keywords !== null && $keywords->count() > 0) {
            foreach ($keywords as $keyword) {
                $keywordValue = $keyword->getKeyword();
                if (!Utils::isEmpty($keywordValue)) {
                    $isBlackListedKeyword = in_array($keywordValue, $blackListedKeywords);
                    if (!$isBlackListedKeyword) {
                        $this->keywords[] = new Keyword($keywordValue);
                    }
                }
            }
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasKeywords(): bool
    {
        return $this->keywords && !empty($this->keywords);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setImages(): void
    {
        $this->images = $this->productImageService->getProductImages($this->product);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasImages(): bool
    {
        return $this->images && !empty($this->images);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function getSalesFrequency(): int
    {
        return $this->salesFrequency;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface\
     */
    protected function setSalesFrequency(): void
    {
        $lastMonthDate = new DateTimeImmutable('-1 month');
        $criteria = new Criteria();
        $criteria->addAssociation('order');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('productId', $this->product->getId()),
            new RangeFilter(
                'order.orderDateTime',
                [RangeFilter::GTE => $lastMonthDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            )
        ]));

        /** @var EntityRepository $orderLineItemRepository */
        $orderLineItemRepository = $this->container->get('order_line_item.repository');
        $orders = $orderLineItemRepository->searchIds($criteria, $this->salesChannelContext->getContext());

        $this->salesFrequency = $orders->getTotal();
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasSalesFrequency(): bool
    {
        // In case a product has no sales, it's sales frequency would still be 0.
        return true;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setUserGroups(): void
    {
        foreach ($this->customerGroups as $customerGroupEntity) {
            $this->userGroups[] = new Usergroup(
                Utils::calculateUserGroupHash($this->shopkey, $customerGroupEntity->getId())
            );
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasUserGroups(): bool
    {
        return $this->userGroups && !empty($this->userGroups);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setOrdernumbers(): void
    {
        $this->setOrdernumberByProduct($this->product);
        foreach ($this->product->getChildren() as $productEntity) {
            $this->setOrdernumberByProduct($productEntity);
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasOrdernumbers(): bool
    {
        return $this->ordernumbers && !empty($this->ordernumbers);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
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
            $value = $this->product->getReleaseDate()->format(DATE_ATOM);
            $this->addProperty('releasedate', $value);
        }

        if ($this->product->getManufacturer() && $this->product->getManufacturer()->getMedia()) {
            $value = $this->product->getManufacturer()->getMedia()->getUrl();
            $this->addProperty('vendorlogo', $value);
        }

        if ($this->product->getPrice()) {
            /** @var ProductPrice $price */
            $price = $this->product->getPrice()->getCurrencyPrice($this->salesChannelContext->getCurrency()->getId());
            if ($price) {
                /** @var ProductPrice $listPrice */
                $listPrice = $price->getListPrice();
                if ($listPrice) {
                    $this->addProperty('old_price', (string) round($listPrice->getGross(), 2));
                    $this->addProperty('old_price_net', (string) round($listPrice->getNet(), 2));
                }
            }
        }

        if (method_exists($this->product, 'getMarkAsTopseller')) {
            $isMarkedAsTopseller = $this->product->getMarkAsTopseller() ?? false;
            $translated = $this->translateBooleanValue($isMarkedAsTopseller);
            $this->addProperty('product_promotion', $translated);
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    public function hasProperties(): bool
    {
        return $this->properties && !empty($this->properties);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setVendors(): void
    {
        $manufacturer = $this->product->getManufacturer();
        if ($manufacturer) {
            $name = $manufacturer->getTranslation('name');
            if (!Utils::isEmpty($name)) {
                $vendorAttribute = new Attribute(
                    'vendor',
                    [$this->decodeHtmlEntity(Utils::removeControlCharacters($name))]
                );
                $this->attributes[] = $vendorAttribute;
            }
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @return Property[]
     */
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
                $properyGroupAttrib->addValue($this->decodeHtmlEntity(
                    Utils::removeControlCharacters($propertyGroupOptionName)
                ));

                $attributes[] = $properyGroupAttrib;
            }
        }

        return $attributes;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setAttributeProperties(): void
    {
        $this->attributes = array_merge($this->attributes, $this->getAttributeProperties($this->product));
        foreach ($this->product->getChildren() as $productEntity) {
            $this->attributes = array_merge($this->attributes, $this->getAttributeProperties($productEntity));
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function setAdditionalAttributes(): void
    {
        $shippingFree = $this->translateBooleanValue($this->product->getShippingFree());
        $this->attributes[] = new Attribute('shipping_free', [$shippingFree]);
        $rating = $this->product->getRatingAverage() ?? 0.0;
        $this->attributes[] = new Attribute('rating', [$rating]);

        // Add custom fields in the attributes array for export
        $this->attributes = array_merge($this->attributes, $this->customFields);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @throws ProductHasNoCategoriesException
     */
    protected function setCategoriesAndCatUrls(): void
    {
        if (!$this->hasCategories()) {
            throw new ProductHasNoCategoriesException($this->product);
        }

        $productCategories = $this->product->getCategories();
        $children = $this->product->getChildren();

        $catUrls = [];
        $categories = [];

        $this->parseCategoryAttributes($productCategories->getElements(), $catUrls, $categories);

        if ($children->count() > 0) {
            foreach ($children as $child) {
                $variantCategories = $child->getCategories();
                if ($variantCategories->count() === 0) {
                    continue;
                }

                $this->parseCategoryAttributes($variantCategories->getElements(), $catUrls, $categories);
            }
        }

        if ($this->dynamicProductGroupService) {
            $dynamicGroupCategories = $this->dynamicProductGroupService->getCategories($this->product->getId());
            $this->parseCategoryAttributes($dynamicGroupCategories, $catUrls, $categories);
        }

        if ($this->isDirectIntegration() && !Utils::isEmpty($catUrls)) {
            $catUrlAttribute = new Attribute('cat_url');
            $catUrlAttribute->setValues($this->decodeHtmlEntities(Utils::flattenWithUnique($catUrls)));
            $this->attributes[] = $catUrlAttribute;
        }

        if (!Utils::isEmpty($categories)) {
            $categoryAttribute = new Attribute('cat');
            $categoryAttribute->setValues($this->decodeHtmlEntities(array_unique($categories)));
            $this->attributes[] = $categoryAttribute;
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @return Price[]
     */
    protected function getPricesFromProduct(ProductEntity $product): array
    {
        $prices = [];
        $productPrice = $product->getPrice();
        if (!$productPrice || !$productPrice->first()) {
            return [];
        }

        $currencyId = $this->salesChannelContext->getSalesChannel()->getCurrencyId();
        $currencyPrice = $productPrice->getCurrencyPrice($currencyId, false);

        // If no currency price is available, fallback to the default price.
        if (!$currencyPrice) {
            $currencyPrice = $productPrice->first();
        }

        foreach ($this->customerGroups as $customerGroup) {
            $userGroupHash = Utils::calculateUserGroupHash($this->shopkey, $customerGroup->getId());
            if (Utils::isEmpty($userGroupHash)) {
                continue;
            }

            $netPrice = $currencyPrice->getNet();
            $grossPrice = $currencyPrice->getGross();
            $price = new Price();
            if ($customerGroup->getDisplayGross()) {
                $price->setValue(round($grossPrice, 2), $userGroupHash);
            } else {
                $price->setValue(round($netPrice, 2), $userGroupHash);
            }

            $prices[] = $price;
        }

        $price = new Price();
        $price->setValue(round($currencyPrice->getGross(), 2));
        $prices[] = $price;

        return $prices;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function getCustomFieldProperties(ProductEntity $product): array
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * @return Attribute[]
     */
    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function translateBooleanValue(bool $value)
    {
        $translationKey = $value ? 'finSearch.general.yes' : 'finSearch.general.no';

        return $this->translator->trans($translationKey);
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function addProperty(string $name, $value): void
    {
        if (!Utils::isEmpty($value)) {
            $property = new Property($name);
            $property->addValue($value);
            $this->properties[] = $property;
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
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
                $this->navigationCategory
            );


            if (!Utils::isEmpty($categoryPath)) {
                if (!in_array($categoryPath, $categories)) {
                    $categories = array_merge($categories, [$categoryPath]);
                }
            }

            // Only export `cat_url`s recursively if integration type is Direct Integration.
            if ($this->isDirectIntegration()) {
                $catUrls = array_merge(
                    $catUrls,
                    $this->urlBuilderService->getCategoryUrls($categoryEntity, $this->salesChannelContext->getContext())
                );
            }
        }
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function isDirectIntegration(): bool
    {
        return $this->config->getIntegrationType() === IntegrationType::DI;
    }

    protected function isApiIntegration(): bool
    {
        return $this->config->getIntegrationType() === IntegrationType::API;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * For API Integrations, we have to remove special characters from the attribute key as a requirement for
     * sending data via API.
     */
    protected function getAttributeKey(?string $key): ?string
    {
        if ($this->isApiIntegration()) {
            return Utils::removeSpecialChars($key);
        }

        return $key;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     */
    protected function decodeHtmlEntities(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = $this->decodeHtmlEntity($value);
        }

        return $values;
    }

    /**
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
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
     * @deprecated FindologicProduct class will be removed in plugin version 5.0
     * Checks if the product, or any of its children has any category assigned.
     */
    protected function hasCategories(): bool
    {
        $productCategories = $this->product->getCategories();
        $childrenWithCategories = $this->product->getChildren()->filter(function (ProductEntity $variant) {
            return $variant->getCategories()->count() > 0;
        });

        return $productCategories->count() > 0 || $childrenWithCategories->count() > 0;
    }
}
