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
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class XmlProduct
{
    /** @var ProductEntity */
    private $product;

    /** @var RouterInterface */
    private $router;

    /** @var Context */
    private $salesChannelContext;

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
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function __construct(
        ProductEntity $product,
        RouterInterface $router,
        ContainerInterface $container,
        SalesChannelContext $salesChannelContext,
        string $shopkey,
        array $customerGroups
    ) {
        $this->product = $product;
        $this->router = $router;
        $this->container = $container;
        $this->salesChannelContext = $salesChannelContext;
        $this->shopkey = $shopkey;
        $this->customerGroups = $customerGroups;

        $this->exporter = Exporter::create(Exporter::TYPE_XML);
        $this->xmlItem = $this->exporter->createItem($product->getId());

        /** @var FindologicProductFactory $findologicProductFactory */
        $findologicProductFactory = $this->container->get(FindologicProductFactory::class);
        $this->findologicProduct = $findologicProductFactory
            ->buildInstance($product, $router, $container, $salesChannelContext, $shopkey, $customerGroups, $this->xmlItem);

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
     */
    private function setDescription(): void
    {
        if ($this->findologicProduct->hasDescription()) {
            $this->xmlItem->addDescription($this->findologicProduct->getDescription());
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    private function setDateAdded(): void
    {
        if ($this->findologicProduct->hasDateAdded()) {
            $this->xmlItem->setDateAdded($this->findologicProduct->getDateAdded());
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    private function setUrl(): void
    {
        if ($this->findologicProduct->hasUrl()) {
            $this->xmlItem->addUrl($this->findologicProduct->getUrl());
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    private function setKeywords(): void
    {
        if ($this->findologicProduct->hasKeywords()) {
            $this->xmlItem->setAllKeywords($this->findologicProduct->getKeywords());
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    private function setImages(): void
    {
        if ($this->findologicProduct->hasImages()) {
            $this->xmlItem->setAllImages($this->findologicProduct->getImages());
        }
    }

    private function setSalesFrequency(): void
    {
        $this->xmlItem->addSalesFrequency($this->findologicProduct->getSalesFrequency());
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    private function setUserGroups(): void
    {
        if ($this->findologicProduct->hasUserGroups()) {
            $this->xmlItem->setAllUsergroups($this->findologicProduct->getUserGroups());
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    private function setOrdernumbers(): void
    {
        if ($this->findologicProduct->hasOrdernumbers()) {
            $this->xmlItem->setAllOrdernumbers($this->findologicProduct->getOrdernumbers());
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    private function setProperties(): void
    {
        if ($this->findologicProduct->hasProperties()) {
            foreach ($this->findologicProduct->getProperties() as $property) {
                $this->xmlItem->addProperty($property);
            }
        }
    }
}
