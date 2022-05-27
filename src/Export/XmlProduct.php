<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Data\DateAdded;
use FINDOLOGIC\Export\Data\Image;
use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\Export\Data\Usergroup;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\Definitions\XmlFields;
use FINDOLOGIC\FinSearch\Export\Logger\ExportExceptionLogger;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

use function sprintf;

class XmlProduct
{
    /** @var ProductEntity */
    private $product;

    /** @var RouterInterface */
    private $router;

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
        string $shopkey,
        array $customerGroups
    ) {
        $this->product = $product;
        $this->router = $router;
        $this->container = $container;
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
        if ($logger) {
            $this->buildWithErrorLogging($logger);
        } else {
            $this->build();
        }
    }

    private function setName(?string $name): void
    {
        $this->xmlItem->addName($name);
    }

    /**
     * @param Attribute[] $attributes
     */
    private function setAttributes(array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $this->xmlItem->addMergedAttribute($attribute);
        }
    }

    /**
     * @param Price[] $prices
     */
    private function setPrices(array $prices): void
    {
        foreach ($prices as $priceData) {
            foreach ($priceData->getValues() as $userGroup => $price) {
                $this->xmlItem->addPrice($price, $userGroup);
            }
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     */
    private function setDescription(?string $description): void
    {
        $this->xmlItem->addDescription($description);
    }

    private function setDateAdded(?DateAdded $dateAdded): void
    {
        $this->xmlItem->setDateAdded($dateAdded);
    }

    private function setUrl(?string $url): void
    {
        $this->xmlItem->addUrl($url);
    }

    /**
     * @param Keyword[] $keywords
     */
    private function setKeywords(array $keywords): void
    {
        $this->xmlItem->setAllKeywords($keywords);
    }

    /**
     * @param Image[] $images
     */
    private function setImages(array $images): void
    {
        $this->xmlItem->setAllImages($images);
    }

    private function setSalesFrequency(int $salesFrequency): void
    {
        $this->xmlItem->addSalesFrequency($salesFrequency);
    }

    /**
     * @param Usergroup[] $userGroups
     */
    private function setUserGroups(array $userGroups): void
    {
        $this->xmlItem->setAllUsergroups($userGroups);
    }

    private function setOrdernumbers(array $ordernumbers): void
    {
        $this->xmlItem->setAllOrdernumbers($ordernumbers);
    }

    /**
     * @param Property[] $properties
     */
    private function setProperties(array $properties): void
    {
        foreach ($properties as $property) {
            $this->xmlItem->addProperty($property);
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoAttributesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     * @throws ProductHasNoCategoriesException
     */
    private function build(): void
    {
        /** @var FindologicProductFactory $findologicProductFactory */
        $findologicProductFactory = $this->container->get(FindologicProductFactory::class);
        $this->findologicProduct = $findologicProductFactory->buildInstance(
            $this->product,
            $this->router,
            $this->container,
            $this->shopkey,
            $this->customerGroups,
            $this->xmlItem
        );

        $this->assertRequiredFieldsAreSet();

        $this->setXmlItemFields();
    }

    private function assertRequiredFieldsAreSet(): void
    {
        if (!$this->findologicProduct->hasName()) {
            throw new ProductHasNoNameException($this->product);
        }

        if (!$this->findologicProduct->hasAttributes()) {
            throw new ProductHasNoAttributesException($this->product);
        }

        if (!$this->findologicProduct->hasPrices()) {
            throw new ProductHasNoPricesException($this->product);
        }
    }

    private function buildWithErrorLogging(LoggerInterface $logger): void
    {
        try {
            $this->build();
        } catch (Throwable $e) {
            $exceptionLogger = new ExportExceptionLogger($logger);

            $exceptionLogger->log($this->product, $e);
            $this->xmlItem = null;
        }
    }

    private function setXmlItemFields(): void
    {
        foreach (XmlFields::KEYS as $requiredField) {
            $field = ucfirst($requiredField);
            $getter = 'get' . $field;
            $setter = 'set' . $field;
            $hasField = 'has' . $field;

            $this->assertItemFieldMethodsExist($getter, $setter, $hasField);

            if ($this->findologicProduct->{$hasField}()) {
                $this->{$setter}($this->findologicProduct->{$getter}());
            }
        }
    }

    private function assertItemFieldMethodsExist(string $getter, string $setter, string $hasField): void
    {
        if (!method_exists($this->findologicProduct, $hasField)) {
            throw new RuntimeException(
                sprintf(
                    'Method %s::%s does not exist.',
                    get_class($this->findologicProduct),
                    $hasField
                )
            );
        }

        if (!method_exists($this, $setter)) {
            throw new RuntimeException(
                sprintf(
                    'Method %s::%s does not exist.',
                    get_class($this),
                    $setter
                )
            );
        }

        if (!method_exists($this->findologicProduct, $getter)) {
            throw new RuntimeException(
                sprintf(
                    'Method %s::%s does not exist.',
                    get_class($this->findologicProduct),
                    $getter
                )
            );
        }
    }
}
