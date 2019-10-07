<?php
declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Pricing\Price as ProductPrice;
use Shopware\Core\Framework\Rule\Container\ContainerInterface;
use Shopware\Core\Framework\Struct\Struct;
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

    /** @var array */
    protected $customerGroups;

    /** @var string */
    protected $name;

    /** @var Attribute[] */
    protected $attributes;

    /** @var array */
    protected $prices;

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

        $this->setName($product->getName());
        $this->setAttributes();
    }

    protected function setName(string $name): void
    {
        $this->name = $name;
    }

    protected function setAttributes(): void
    {
        $this->setCategoriesAndCatUrls();
    }

    /**
     * @throws ProductHasNoCategoriesException
     */
    private function setCategoriesAndCatUrls(): void
    {
        if (!$this->product->getCategories()) {
            throw new ProductHasNoCategoriesException(sprintf('%s has no category', $this->name));
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

            $catUrl = $this->router->generate(
                'frontend.navigation.page',
                ['navigationId' => $categoryEntity->getId()],
                RouterInterface::ABSOLUTE_PATH
            );

            if (!empty($catUrl)) {
                $catUrls[] = $catUrl;
            }

            $breadCrumbs = $categoryEntity->getBreadcrumb();
            array_shift($breadCrumbs);
            $categoryPath = implode('_', $breadCrumbs);

            if (!empty($categoryPath)) {
                $categories[] = $categoryPath;
            }
        }

        if ($catUrls) {
            $catUrlAttribute->setValues(array_unique($catUrls));
        }

        if ($categories) {
            $categoryAttribute->setValues(array_unique($categories));
        }

        $this->attributes = [
            $categoryAttribute,
            $catUrlAttribute
        ];
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

        $this->prices = [];

        /** @var ProductEntity $variant */
        foreach ($this->product->getChildren() as $variant) {
            if (!$variant->getActive() || $variant->getStock() < 0) {
                continue;
            }

            $this->prices = array_merge($this->prices, $this->getPricesFromProduct($variant));
        }
    }

    private function getPricesFromProduct(ProductEntity $variant): array
    {
        $prices = [];

        /** @var ProductPrice $item */
        foreach ($variant->getPrice() as $item) {
            /** @var CustomerGroupEntity $customerGroup */
            foreach ($this->customerGroups as $customerGroup) {
                $price = new Price();
                if ($customerGroup->getDisplayGross()) {
                    $price->setValue($item->getGross(),
                        Utils::calculateUserGroupHash($this->shopkey, $customerGroup->getId()));
                } else {
                    $price->setValue($item->getNet(),
                        Utils::calculateUserGroupHash($this->shopkey, $customerGroup->getId()));
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
    public function setProductPrices(): void
    {
        $prices = $this->getPricesFromProduct($this->product);
        if (empty($prices)) {
            throw new ProductHasNoPricesException(sprintf('%s has no price set', $this->name));
        }

        $this->prices = array_merge($this->prices, $prices);
    }

    public function hasName(): bool
    {
        return $this->name && empty($this->name);
    }

    public function hasAttributes(): bool
    {
        return $this->attributes && !empty($this->attributes);
    }

    public function hasPrices(): bool
    {
        return $this->prices && !empty($this->prices);
    }

    public function getName(): string
    {
        if (!$this->hasName()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->name;
    }

    public function getAttributes(): array
    {
        if (!$this->hasAttributes()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->attributes;
    }

    public function getPrices(): array
    {
        if (!$this->hasPrices()) {
            throw new AccessEmptyPropertyException();
        }

        return $this->prices;
    }
}
