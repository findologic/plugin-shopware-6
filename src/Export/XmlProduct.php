<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\FinSearch\Exceptions\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoDateAddedException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoDescriptionException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoImagesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoKeywordsException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoOrdernumbersException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPropertiesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoURLException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoUserGroupsException;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\Routing\RouterInterface;

class XmlProduct
{
    /** @var ProductEntity */
    private $product;

    /** @var RouterInterface */
    private $router;

    /** @var Context */
    private $context;

    /** @var ContainerInterface */
    private $container;

    /** @var string */
    private $shopkey;

    /** @var CustomerGroupEntity[] */
    private $customerGroups;

    /** @var Item */
    private $xmlItem;

    /** @var Exporter */
    private $exporter;

    /** @var FindologicProduct */
    private $findologicProduct;

    /**
     * @param CustomerGroupEntity[] $customerGroups
     *
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoAttributesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     * @throws ProductHasNoCategoriesException
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

        $this->exporter = Exporter::create(Exporter::TYPE_XML);
        $this->xmlItem = $this->exporter->createItem($product->getId());

        $this->findologicProduct = $this->container->get(FindologicProductFactory::class)
            ->buildInstance($product, $router, $container, $context, $shopkey, $customerGroups);

        $this->buildXmlItem();
    }

    public function getXmlItem(): Item
    {
        return $this->xmlItem;
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoAttributesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    private function buildXmlItem(): void
    {
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
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoNameException
     */
    private function setName(): void
    {
        if (!$this->findologicProduct->hasName()) {
            throw new ProductHasNoNameException();
        }

        $this->xmlItem->addName($this->findologicProduct->getName());
    }

    /**
     * @throws ProductHasNoAttributesException
     * @throws AccessEmptyPropertyException
     */
    private function setAttributes(): void
    {
        if (!$this->findologicProduct->hasAttributes()) {
            throw new ProductHasNoAttributesException();
        }

        /** @var Attribute $attribute */
        foreach ($this->findologicProduct->getAttributes() as $attribute) {
            $this->xmlItem->addMergedAttribute($attribute);
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoPricesException
     */
    private function setPrices(): void
    {
        if (!$this->findologicProduct->hasPrices()) {
            throw new ProductHasNoPricesException();
        }

        /** @var Price $priceData */
        foreach ($this->findologicProduct->getPrices() as $priceData) {
            foreach ($priceData->getValues() as $userGroup => $price) {
                $this->xmlItem->addPrice($price, $userGroup);
            }
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoDescriptionException
     */
    private function setDescription()
    {
        if (!$this->findologicProduct->hasDescription()) {
            throw new ProductHasNoDescriptionException();
        }

        $this->xmlItem->addDescription($this->findologicProduct->getDescription());
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoDateAddedException
     */
    private function setDateAdded()
    {
        if (!$this->findologicProduct->hasDateAdded()) {
            throw new ProductHasNoDateAddedException();
        }

        $this->xmlItem->setDateAdded($this->findologicProduct->getDateAdded());
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoURLException
     */
    private function setUrl()
    {
        if (!$this->findologicProduct->hasUrl()) {
            throw new ProductHasNoURLException();
        }

        $this->xmlItem->addUrl($this->findologicProduct->getUrl());
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoKeywordsException
     */
    private function setKeywords(): void
    {
        if (!$this->findologicProduct->hasKeywords()) {
            throw new ProductHasNoKeywordsException();
        }

        $this->xmlItem->setAllKeywords($this->findologicProduct->getKeywords());
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoImagesException
     */
    private function setImages()
    {
        if (!$this->findologicProduct->hasImages()) {
            throw new ProductHasNoImagesException();
        }

        $this->xmlItem->setAllImages($this->findologicProduct->getImages());
    }

    private function setSalesFrequency()
    {
        $this->xmlItem->addSalesFrequency($this->findologicProduct->getSalesFrequency());
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoUserGroupsException
     */
    private function setUserGroups()
    {
        if (!$this->findologicProduct->hasUserGroups()) {
            throw new ProductHasNoUserGroupsException();
        }

        $this->xmlItem->setAllUsergroups($this->findologicProduct->getUserGroups());
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoOrdernumbersException
     */
    private function setOrdernumbers()
    {
        if (!$this->findologicProduct->hasOrdernumbers()) {
            throw new ProductHasNoOrdernumbersException();
        }

        $this->xmlItem->setAllOrdernumbers($this->findologicProduct->getOrdernumbers());
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoPropertiesException
     */
    private function setProperties()
    {
        if (!$this->findologicProduct->hasProperties()) {
            throw new ProductHasNoPropertiesException();
        }

        foreach ($this->findologicProduct->getProperties() as $property) {
            $this->xmlItem->addProperty($property);
        }
    }
}
