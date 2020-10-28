<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\Export\Exceptions\EmptyValueNotAllowedException;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasCrossSellingCategoryException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductInvalidException;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

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

        $this->exporter = Exporter::create(Exporter::TYPE_XML);
        $this->xmlItem = $this->exporter->createItem($product->getId());
    }

    public function getXmlItem(): ?Item
    {
        return $this->xmlItem;
    }

    /**
     * Builds the XML Item. In case a logger is given, the exceptions are logged instead of thrown.
     *
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoAttributesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function buildXmlItem(?LoggerInterface $logger = null): void
    {
        if (!$logger) {
            $this->build();
            return;
        }

        $this->buildWithErrorLogging($logger);
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoNameException
     */
    private function setName(): void
    {
        if (!$this->findologicProduct->hasName()) {
            throw new ProductHasNoNameException($this->product);
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
            throw new ProductHasNoAttributesException($this->product);
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
            throw new ProductHasNoPricesException($this->product);
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

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoAttributesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    private function build(): void
    {
        /** @var FindologicProductFactory $findologicProductFactory */
        $findologicProductFactory = $this->container->get(FindologicProductFactory::class);
        $this->findologicProduct = $findologicProductFactory->buildInstance(
            $this->product,
            $this->router,
            $this->container,
            $this->context,
            $this->shopkey,
            $this->customerGroups,
            $this->xmlItem
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

    private function buildWithErrorLogging(LoggerInterface $logger): void
    {
        try {
            $this->build();
        } catch (ProductInvalidException $e) {
            $this->logProductInvalidException($logger, $e);
            $this->xmlItem = null;
        } catch (EmptyValueNotAllowedException $e) {
            $logger->warning(sprintf(
                'Product with id "%s" could not be exported. It appears to have empty values assigned to it. ' .
                'If you see this message in your logs, please report this as a bug.',
                $this->product->getId()
            ));
            $this->xmlItem = null;
        } catch (Throwable $e) {
            $logger->warning(sprintf(
                'Error while exporting the product with id "%s". If you see this message in your logs, ' .
                'please report this as a bug. Error message: %s',
                $this->product->getId(),
                $e->getMessage()
            ));
            $this->xmlItem = null;
        }
    }

    private function logProductInvalidException(LoggerInterface $logger, ProductInvalidException $e): void
    {
        switch (get_class($e)) {
            case AccessEmptyPropertyException::class:
                $message = sprintf(
                    'Product with id %s was not exported because the property does not exist',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoAttributesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no attributes',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoNameException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no name set',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoPricesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no price associated to it',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoCategoriesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no categories assigned',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasCrossSellingCategoryException::class:
                $message = sprintf(
                    'Product with id %s (%s) was not exported because it ' .
                    'is assigned to cross selling category %s (%s)',
                    $e->getProduct()->getId(),
                    $e->getProduct()->getName(),
                    $e->getCategory()->getId(),
                    implode(' > ', $e->getCategory()->getBreadcrumb())
                );
                break;
            default:
                $message = sprintf(
                    'Product with id %s could not be exported.',
                    $e->getProduct()->getId()
                );
        }

        $logger->warning($message, ['exception' => $e]);
    }
}
