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
            $this->xmlItem->addAttribute($attribute);
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
}
