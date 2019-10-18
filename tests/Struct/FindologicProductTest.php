<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\Export\Data\Attribute;
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
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Tests\ProductHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Routing\RouterInterface;

class FindologicProductTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;

    /** @var Context */
    private $defaultContext;

    /** @var string */
    private $shopkey;

    /** @var RouterInterface */
    private $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = $this->getContainer()->get('router');
        $this->defaultContext = Context::createDefaultContext();
        $this->shopkey = '80AB18D4BE2654E78244106AD315DC2C';
    }

    public function productNameProvider(): array
    {
        return [
            'Product name is empty' => ['', ProductHasNoNameException::class],
            'Product name is null value' => [null, ProductHasNoNameException::class],
            'Product name is "Findologic Test"' => ['Findologic Test', null],
        ];
    }

    /**
     * @dataProvider productNameProvider
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testProductName(?string $name, ?string $exception): void
    {
        if ($exception) {
            $this->expectException($exception);
        }

        $productEntity = $this->createTestProduct();
        $productEntity->setName($name);

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct =
            $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            []
        );

        if (!$exception) {
            $this->assertTrue($findologicProduct->hasName());
            $this->assertSame($name, $findologicProduct->getName());
        } else {
            $this->assertFalse($findologicProduct->hasName());
        }
    }

    public function categoriesProvider(): array
    {
        return [
            'Product has no category' => [false, ProductHasNoCategoriesException::class],
            'Product has a category' => [true, null],
        ];
    }

    /**
     * @dataProvider categoriesProvider
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testProductCategories(bool $hasCategory, ?string $exception): void
    {
        if ($exception) {
            $this->expectException($exception);
        }

        $productEntity = $this->createTestProduct();

        if (!$hasCategory) {
            $productEntity->setCategories(new CategoryCollection([]));
        }

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            []
        );

        if (!$exception) {
            $this->assertTrue($findologicProduct->hasAttributes());
            $attribute = current($findologicProduct->getAttributes());
            $this->assertSame('cat_url', $attribute->getKey());
            $this->assertSame(sprintf('/navigation/%s', $productEntity->getId()), current($attribute->getValues()));
        }
    }

    public function priceProvider(): array
    {
        $price = new Price();
        $price->setValue(15);

        return [
            'Product has no prices' => [null, ProductHasNoPricesException::class],
            'Product has price set' => [$price, null]
        ];
    }

    /**
     * @dataProvider priceProvider
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testProductPrices(?Price $price, ?string $exception): void
    {
        if ($exception) {
            $this->expectException($exception);
        }

        $productEntity = $this->createTestProduct();

        if (!$price) {
            $productEntity->setPrice(new PriceCollection([]));
        }

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct =
            $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            []
        );

        if (!$exception) {
            $this->assertTrue($findologicProduct->hasPrices());
            $this->assertEquals($price, current($findologicProduct->getPrices()));
        } else {
            $this->assertFalse($findologicProduct->hasPrices());
        }
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     * @throws InconsistentCriteriaIdsException
     */
    public function testProduct(): void
    {
        $productEntity = $this->createTestProduct();

        $productUrl = $this->router->generate(
            'frontend.detail.page',
            ['productId' => $productEntity->getId()],
            RouterInterface::ABSOLUTE_URL
        );

        $productTag = new Keyword('Findologic Tag');
        $images = $this->getImages();
        $attributes = $this->getAttributes($productEntity);

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->defaultContext)
            ->getElements();

        $userGroup = $this->getUserGroups($customerGroupEntities);
        $ordernumbers = $this->getOrdernumber($productEntity);
        $properties = $this->getProperties($productEntity);

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            $customerGroupEntities
        );

        $this->assertEquals($productEntity->getName(), $findologicProduct->getName());
        $this->assertEquals($productUrl, $findologicProduct->getUrl());
        $this->assertEquals([$productTag], $findologicProduct->getKeywords());
        $this->assertEquals($images, $findologicProduct->getImages());
        $this->assertEquals(0, $findologicProduct->getSalesFrequency());
        $this->assertEquals($attributes, $findologicProduct->getAttributes());
        $this->assertEquals($userGroup, $findologicProduct->getUserGroups());
        $this->assertEquals($ordernumbers, $findologicProduct->getOrdernumbers());
        $this->assertEquals($properties, $findologicProduct->getProperties());
    }

    /**
     * @return Property[]
     */
    private function getProperties(ProductEntity $productEntity): array
    {
        $properties = [];

        if ($productEntity->getTax()) {
            $properties[] = new Property('tax', ['tax' => $productEntity->getTax()->getTaxRate()]);
        }

        if ($productEntity->getDeliveryDate()->getLatest()) {
            $properties[] = new Property('latestdeliverydate', [
                'latestdeliverydate' =>
                    $productEntity->getDeliveryDate()->getLatest()->format(DATE_ATOM)
            ]);
        }

        if ($productEntity->getDeliveryDate()->getEarliest()) {
            $properties[] = new Property('earliestdeliverydate', [
                'earliestdeliverydate' =>
                    $productEntity->getDeliveryDate()->getEarliest()->format(DATE_ATOM)
            ]);
        }

        if ($productEntity->getPurchaseUnit()) {
            $properties[] = new Property('purchaseunit', ['purchaseunit' => $productEntity->getPurchaseUnit()]);
        }

        if ($productEntity->getReferenceUnit()) {
            $properties[] = new Property('referenceunit', [
                'referenceunit' => $productEntity->getReferenceUnit()
            ]);
        }

        if ($productEntity->getPackUnit()) {
            $properties[] = new Property('packunit', ['packunit' => $productEntity->getPackUnit()]);
        }

        if ($productEntity->getStock()) {
            $properties[] = new Property('stock', ['stock' => $productEntity->getStock()]);
        }

        if ($productEntity->getAvailableStock()) {
            $properties[] = new Property('availableStock', [
                'availableStock' => $productEntity->getAvailableStock()
            ]);
        }

        if ($productEntity->getWeight()) {
            $properties[] = new Property('weight', ['weight' => $productEntity->getWeight()]);
        }

        if ($productEntity->getWidth()) {
            $properties[] = new Property('width', ['width' => $productEntity->getWidth()]);
        }

        if ($productEntity->getHeight()) {
            $properties[] = new Property('height', ['height' => $productEntity->getHeight()]);
        }

        if ($productEntity->getLength()) {
            $properties[] = new Property('length', ['length' => $productEntity->getLength()]);
        }

        if ($productEntity->getReleaseDate()) {
            $properties[] = new Property('releasedate', [
                'releasedate' => $productEntity->getReleaseDate()->format(DATE_ATOM)
            ]);
        }

        if ($productEntity->getManufacturer() && $productEntity->getManufacturer()->getMedia()) {
            $properties[] = new Property('vendorlogo', [
                'vendorlogo' => $productEntity->getManufacturer()->getMedia()->getUrl()
            ]);
        }

        return $properties;
    }

    /**
     * @return Ordernumber[]
     */
    private function getOrdernumber(ProductEntity $productEntity): array
    {
        $ordernumbers = [];
        if ($productEntity->getProductNumber()) {
            $ordernumbers[] = new Ordernumber($productEntity->getProductNumber());
        }
        if ($productEntity->getEan()) {
            $ordernumbers[] = new Ordernumber($productEntity->getEan());
        }

        if ($productEntity->getManufacturerNumber()) {
            $ordernumbers[] = new Ordernumber($productEntity->getManufacturerNumber());
        }

        return $ordernumbers;
    }

    /**
     * @param CustomerGroupEntity[]
     *
     * @return Usergroup[]
     */
    private function getUserGroups(array $customerGroupEntities): array
    {
        $userGroup = [];

        /** @var CustomerGroupEntity $customerGroupEntity */
        foreach ($customerGroupEntities as $customerGroupEntity) {
            $userGroup[] = new Usergroup(
                Utils::calculateUserGroupHash($this->shopkey, $customerGroupEntity->getId())
            );
        }

        return $userGroup;
    }

    /**
     * @return Attribute[]
     */
    private function getAttributes(ProductEntity $productEntity): array
    {
        $catUrl = $this->router->generate(
            'frontend.navigation.page',
            ['navigationId' => $productEntity->getCategories()->first()->getId()],
            RouterInterface::ABSOLUTE_PATH
        );

        $attributes = [];
        $catUrlAttribute = new Attribute('cat_url', [$catUrl]);
        $vendorAttribute = new Attribute('vendor', [$productEntity->getManufacturer()->getName()]);

        $attributes[] = $catUrlAttribute;
        $attributes[] = $vendorAttribute;

        $attributes[] = new Attribute(
            $productEntity->getProperties()
                ->first()->getGroup()->getName(),
            [
                $productEntity->getProperties()
                    ->first()->getName()
            ]
        );
        $attributes[] = new Attribute(
            $productEntity->getProperties()
                ->first()
                ->getProductConfiguratorSettings()
                ->first()
                ->getOption()
                ->getGroup()
                ->getName(),
            [
                $productEntity->getProperties()
                    ->first()
                    ->getProductConfiguratorSettings()
                    ->first()
                    ->getOption()
                    ->getName()
            ]
        );
        $attributes[] = new Attribute('shipping_free', [$productEntity->getShippingFree() ? 1 : 0]);

        return $attributes;
    }

    /**
     * @return Image[]
     */
    private function getImages(): array
    {
        $images = [];
        $requestContext = $this->router->getContext();
        $schemaAuthority = $requestContext->getScheme() . '://' . $requestContext->getHost();
        if ($requestContext->getHttpPort() !== 80) {
            $schemaAuthority .= ':' . $requestContext->getHttpPort();
        } elseif ($requestContext->getHttpsPort() !== 443) {
            $schemaAuthority .= ':' . $requestContext->getHttpsPort();
        }

        $fallbackImage = sprintf(
            '%s/%s',
            $schemaAuthority,
            'bundles/storefront/assets/icon/default/placeholder.svg'
        );

        $images[] = new Image($fallbackImage);
        $images[] = new Image($fallbackImage, Image::TYPE_THUMBNAIL);

        return $images;
    }
}
