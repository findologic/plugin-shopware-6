<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Data\DateAdded;
use FINDOLOGIC\Export\Data\Image;
use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\Export\Data\Ordernumber;
use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\Export\Data\Usergroup;
use FINDOLOGIC\FinSearch\Exceptions\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price as ProductPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Tag\TagEntity;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class FindologicProduct extends Struct
{
    /** @var ProductEntity */
    protected $product;

    /** @var RouterInterface */
    protected $router;

    /** @var ContainerInterface */
    protected $container;

    /** @var Context */
    protected $context;

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
        Context $context,
        string $shopkey,
        array $customerGroups
    ) {
        $this->product = $product;
        $this->router = $router;
        $this->container = $container;
        $this->context = $context;
        $this->shopkey = $shopkey;
        $this->customerGroups = $customerGroups;
        $this->prices = [];
        $this->attributes = [];
        $this->properties = [];

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
     * @throws ProductHasNoNameException
     */
    protected function setName(): void
    {
        if (empty($this->product->getName())) {
            throw new ProductHasNoNameException();
        }

        $this->name = Utils::removeControlCharacters($this->product->getName());
    }

    /**
     * @throws ProductHasNoCategoriesException
     */
    protected function setAttributes(): void
    {
        $this->setCategoriesAndCatUrls();
        $this->setVendors();
        $this->setAttributeProperties();
        $this->setAdditionalAttributes();
    }

    /**
     * @throws ProductHasNoCategoriesException
     */
    private function setCategoriesAndCatUrls(): void
    {
        if (!$this->product->getCategories() || empty($this->product->getCategories()->count())) {
            throw new ProductHasNoCategoriesException();
        }

        /** @var Attribute $categoryAttribute */
        $categoryAttribute = new Attribute('cat');

        /** @var Attribute $catUrlAttribute */
        $catUrlAttribute = new Attribute('cat_url');

        $catUrls = [];
        $categories = [];

        /** @var CategoryEntity $categoryEntity */
        foreach ($this->product->getCategories() as $categoryEntity) {
            if (!$categoryEntity->getActive()) {
                continue;
            }

            $seoUrls = $this->fetchCategorySeoUrls($categoryEntity);
            if ($seoUrls->count() > 0) {
                foreach ($seoUrls->getElements() as $seoUrlEntity) {
                    $catUrl = $seoUrlEntity->getSeoPathInfo();
                    if (!empty(trim($catUrl))) {
                        $catUrls[] = sprintf('/%s/', trim($catUrl, '/'));
                    }
                }
            }

            $catUrl = sprintf(
                '/%s/',
                trim(
                    $this->router->generate(
                        'frontend.navigation.page',
                        ['navigationId' => $categoryEntity->getId()],
                        RouterInterface::ABSOLUTE_PATH
                    ),
                    '/'
                )
            );

            if (!empty($catUrl)) {
                $catUrls[] = $catUrl;
            }

            $categoryPath = $this->buildCategoryPath($categoryEntity);

            if (!empty($categoryPath)) {
                $categories[] = $categoryPath;
            }
        }

        if (!empty($catUrls)) {
            $catUrlAttribute->setValues(array_unique($catUrls));
            $this->attributes[] = $catUrlAttribute;
        }

        if (!empty($categories)) {
            $categoryAttribute->setValues(array_unique($categories));
            $this->attributes[] = $categoryAttribute;
        }
    }

    /**
     * @throws ProductHasNoPricesException
     */
    protected function setPrices(): void
    {
        $this->setVariantPrices();
        $this->setProductPrices();
    }

    private function setVariantPrices(): void
    {
        if (!$this->product->getChildCount()) {
            return;
        }

        /** @var ProductEntity $variant */
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
    private function getPricesFromProduct(ProductEntity $variant): array
    {
        $prices = [];

        /** @var ProductPrice $item */
        foreach ($variant->getPrice() as $item) {
            /** @var CustomerGroupEntity $customerGroup */
            foreach ($this->customerGroups as $customerGroup) {
                $price = new Price();
                if ($customerGroup->getDisplayGross()) {
                    $price->setValue(
                        $item->getGross(),
                        Utils::calculateUserGroupHash($this->shopkey, $customerGroup->getId())
                    );
                } else {
                    $price->setValue(
                        $item->getNet(),
                        Utils::calculateUserGroupHash($this->shopkey, $customerGroup->getId())
                    );
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
    private function setProductPrices(): void
    {
        $prices = $this->getPricesFromProduct($this->product);
        if (empty($prices)) {
            throw new ProductHasNoPricesException();
        }

        $this->prices = array_merge($this->prices, $prices);
    }

    public function hasName(): bool
    {
        return $this->name && !empty($this->name);
    }

    public function hasAttributes(): bool
    {
        return $this->attributes && !empty($this->attributes);
    }

    public function hasPrices(): bool
    {
        return $this->prices && !empty($this->prices);
    }

    public function hasDescription(): bool
    {
        return $this->description && !empty($this->description);
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    public function getName(): string
    {
        if (!$this->hasName()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->name;
    }

    /**
     * @return Attribute[]
     * @throws AccessEmptyPropertyException
     */
    public function getAttributes(): array
    {
        if (!$this->hasAttributes()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->attributes;
    }

    /**
     * @return Price[]
     * @throws AccessEmptyPropertyException
     */
    public function getPrices(): array
    {
        if (!$this->hasPrices()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->prices;
    }

    protected function setDescription(): void
    {
        if (!$this->product->getDescription()) {
            $this->description = Utils::cleanString($this->product->getDescription());
        }
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

    protected function setDateAdded(): void
    {
        $createdAt = $this->product->getCreatedAt();
        if ($createdAt !== null) {
            $dateAdded = new DateAdded();
            $dateAdded->setDateValue($createdAt);
            $this->dateAdded = $dateAdded;
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

    public function hasDateAdded(): bool
    {
        return $this->dateAdded && !empty($this->dateAdded);
    }

    private function setUrl(): void
    {
        if (!$this->product->hasExtension('canonicalUrl')) {
            $productUrl = $this->router->generate(
                'frontend.detail.page',
                ['productId' => $this->product->getId()],
                RouterInterface::ABSOLUTE_URL
            );
        } else {
            /** @var SeoUrlEntity $canonical */
            $canonical = $this->product->getExtension('canonicalUrl');
            $productUrl = $canonical->getUrl();
        }

        $this->url = $productUrl;
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

    public function hasUrl(): bool
    {
        return $this->url && !empty($this->url);
    }

    private function setKeywords(): void
    {
        $tags = $this->product->getTags();
        if ($tags !== null && $tags->count() > 0) {
            /** @var TagEntity $tag */
            foreach ($tags as $tag) {
                $this->keywords[] = new Keyword($tag->getName());
            }
        }
    }

    /**
     * @return Keyword[]
     * @throws AccessEmptyPropertyException
     */
    public function getKeywords(): array
    {
        if (!$this->hasKeywords()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->keywords;
    }

    public function hasKeywords(): bool
    {
        return $this->keywords && !empty($this->keywords);
    }

    private function setImages(): void
    {
        if (!$this->product->getMedia() || !$this->product->getMedia()->count()) {
            $fallbackImage = $this->buildFallbackImage($this->router->getContext());

            $this->images[] = new Image($fallbackImage);
            $this->images[] = new Image($fallbackImage, Image::TYPE_THUMBNAIL);

            return;
        }

        /** @var ProductMediaEntity $mediaEntity */
        foreach ($this->product->getMedia() as $mediaEntity) {
            if (!$mediaEntity->getMedia() || !$mediaEntity->getMedia()->getUrl()) {
                continue;
            }

            $this->images[] = new Image($mediaEntity->getMedia()->getUrl());

            $thumbnails = $mediaEntity->getMedia()->getThumbnails();
            if (!$thumbnails) {
                continue;
            }

            /** @var MediaThumbnailEntity $thumbnailEntity */
            foreach ($thumbnails as $thumbnailEntity) {
                $this->images[] = new Image($thumbnailEntity->getUrl(), Image::TYPE_THUMBNAIL);
            }
        }
    }

    /**
     * @return Image[]
     * @throws AccessEmptyPropertyException
     */
    public function getImages(): array
    {
        if (!$this->hasImages()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->images;
    }

    public function hasImages(): bool
    {
        return $this->images && !empty($this->images);
    }

    private function buildFallbackImage(RequestContext $requestContext): string
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

    private function setSalesFrequency(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter(
                'payload.productNumber',
                $this->product->getProductNumber()
            )
        );

        $orders = $this->container->get('order_line_item.repository')->search($criteria, $this->context);
        $this->salesFrequency = $orders->count();
    }

    public function getSalesFrequency(): int
    {
        return $this->salesFrequency;
    }

    protected function setVendors(): void
    {
        if ($this->product->getManufacturer() && $this->product->getManufacturer()->getName()) {
            $vendorAttribute = new Attribute(
                'vendor',
                [Utils::removeControlCharacters($this->product->getManufacturer()->getName())]
            );

            $this->attributes[] = $vendorAttribute;
        }
    }

    /**
     * @return Attribute[]
     */
    protected function getAttributeProperties(
        ProductEntity $productEntity
    ): array {
        $attributes = [];

        foreach ($productEntity->getProperties() as $propertyGroupOptionEntity) {
            $group = $propertyGroupOptionEntity->getGroup();
            if ($group && $propertyGroupOptionEntity->getName() && $group->getName()) {
                $properyGroupAttrib = new Attribute(
                    Utils::removeControlCharacters($group->getName()),
                    [Utils::removeControlCharacters($propertyGroupOptionEntity->getName())]
                );

                $attributes[] = $properyGroupAttrib;
            }

            foreach ($propertyGroupOptionEntity->getProductConfiguratorSettings() as $setting) {
                $group = $setting->getOption()->getGroup();
                if ($group && $setting->getOption() && $group->getName() && $setting->getOption()->getName()) {
                    $configAttrib = new Attribute(
                        Utils::removeControlCharacters($setting->getOption()->getGroup()->getName()),
                        [Utils::removeControlCharacters($setting->getOption()->getName())]
                    );

                    $attributes[] = $configAttrib;
                }
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
        $this->attributes[] = new Attribute('shipping_free', [$this->product->getShippingFree() ? 1 : 0]);
    }

    protected function setUserGroups(): void
    {
        foreach ($this->customerGroups as $customerGroupEntity) {
            $this->userGroups[] = new Usergroup(
                Utils::calculateUserGroupHash($this->shopkey, $customerGroupEntity->getId())
            );
        }
    }

    /**
     * @return Usergroup[]
     * @throws AccessEmptyPropertyException
     */
    public function getUserGroups(): array
    {
        if (!$this->hasUserGroups()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->userGroups;
    }

    public function hasUserGroups(): bool
    {
        return $this->userGroups && !empty($this->userGroups);
    }

    protected function setOrdernumbers(): void
    {
        $this->setOrdernumberByProduct($this->product);
        foreach ($this->product->getChildren() as $productEntity) {
            $this->setOrdernumberByProduct($productEntity);
        }
    }

    protected function setOrdernumberByProduct(ProductEntity $product): void
    {
        if ($product->getProductNumber()) {
            $this->ordernumbers[] = new Ordernumber($product->getProductNumber());
        }
        if ($product->getEan()) {
            $this->ordernumbers[] = new Ordernumber($product->getEan());
        }
        if ($product->getManufacturerNumber()) {
            $this->ordernumbers[] = new Ordernumber($product->getManufacturerNumber());
        }
    }

    /**
     * @return Ordernumber[]
     * @throws AccessEmptyPropertyException
     */
    public function getOrdernumbers(): array
    {
        if (!$this->hasOrdernumbers()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->ordernumbers;
    }

    public function hasOrdernumbers(): bool
    {
        return $this->ordernumbers && !empty($this->ordernumbers);
    }

    protected function setProperties(): void
    {
        if ($this->product->getTax()) {
            $property = new Property('tax');
            $property->addValue((string)$this->product->getTax()->getTaxRate());
            $this->properties[] = $property;
        }

        if ($this->product->getDeliveryDate()->getLatest()) {
            $property = new Property('latestdeliverydate');
            $property->addValue($this->product->getDeliveryDate()->getLatest()->format(DATE_ATOM));
            $this->properties[] = $property;
        }

        if ($this->product->getDeliveryDate()->getEarliest()) {
            $property = new Property('earliestdeliverydate');
            $property->addValue($this->product->getDeliveryDate()->getEarliest()->format(DATE_ATOM));
            $this->properties[] = $property;
        }

        if ($this->product->getPurchaseUnit()) {
            $property = new Property('purchaseunit');
            $property->addValue((string)$this->product->getPurchaseUnit());
            $this->properties[] = $property;
        }

        if ($this->product->getReferenceUnit()) {
            $property = new Property('referenceunit');
            $property->addValue((string)$this->product->getReferenceUnit());
            $this->properties[] = $property;
        }

        if ($this->product->getPackUnit()) {
            $property = new Property('packunit');
            $property->addValue((string)$this->product->getPackUnit());
            $this->properties[] = $property;
        }

        if ($this->product->getStock()) {
            $property = new Property('stock');
            $property->addValue((string)$this->product->getStock());
            $this->properties[] = $property;
        }

        if ($this->product->getAvailableStock()) {
            $property = new Property('availableStock');
            $property->addValue((string)$this->product->getAvailableStock());
            $this->properties[] = $property;
        }

        if ($this->product->getWeight()) {
            $property = new Property('weight');
            $property->addValue((string)$this->product->getWeight());
            $this->properties[] = $property;
        }

        if ($this->product->getWidth()) {
            $property = new Property('width');
            $property->addValue((string)$this->product->getWidth());
            $this->properties[] = $property;
        }

        if ($this->product->getHeight()) {
            $property = new Property('height');
            $property->addValue((string)$this->product->getHeight());
            $this->properties[] = $property;
        }

        if ($this->product->getLength()) {
            $property = new Property('length');
            $property->addValue((string)$this->product->getLength());
            $this->properties[] = $property;
        }

        if ($this->product->getReleaseDate()) {
            $property = new Property('releasedate');
            $property->addValue((string)$this->product->getReleaseDate()->format(DATE_ATOM));
            $this->properties[] = $property;
        }

        if ($this->product->getManufacturer() && $this->product->getManufacturer()->getMedia()) {
            $property = new Property('vendorlogo');
            $property->addValue($this->product->getManufacturer()->getMedia()->getUrl());
            $this->properties[] = $property;
        }
    }

    /**
     * @return Property[]
     * @throws AccessEmptyPropertyException
     */
    public function getProperties(): array
    {
        if (!$this->hasProperties()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->properties;
    }

    public function hasProperties(): bool
    {
        return $this->properties && !empty($this->properties);
    }

    private function fetchCategorySeoUrls(CategoryEntity $categoryEntity): SeoUrlCollection
    {
        return $categoryEntity->getSeoUrls();
    }

    private function buildCategoryPath(CategoryEntity $categoryEntity): string
    {
        $breadCrumbs = $categoryEntity->getBreadcrumb();
        array_shift($breadCrumbs);

        return implode('_', $breadCrumbs);
    }
}
