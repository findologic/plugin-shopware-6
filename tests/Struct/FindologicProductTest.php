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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\RouterInterface;

class FindologicProductTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;

    /** @var Context */
    private $defaultContext;

    /** @var string */
    private $shopkey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultContext = Context::createDefaultContext();
        $this->shopkey = '80AB18D4BE2654E78244106AD315DC2C';
    }

    /**
     * @return array
     */
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
            $this->getContainer()->get('router'),
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

    /**
     * @return array
     */
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
        $findologicProduct =
            $findologicProductFactory->buildInstance(
            $productEntity,
            $this->getContainer()->get('router'),
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

    /**
     * @return array
     */
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
            $this->getContainer()->get('router'),
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
    public function testProduct()
    {
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $productData = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'ean' => Uuid::randomHex(),
            'description' => 'some long description text',
            'tags' => [
                ['id' => $id, 'name' => 'Findologic Tag']
            ],
            'name' => 'Test name',
            'manufacturerNumber' => Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'FINDOLOGIC'],
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'categories' => [
                ['id' => $id, 'name' => 'Test Category'],
            ],
            'translations' => [
                'en-GB' => [
                    'customTranslated' => [
                        'root' => 'test',
                    ],
                ],
                'de-DE' => [
                    'customTranslated' => null,
                ],
            ],
            'properties' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => 'color'],
                ]
            ],
            'options' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => $colorId],
                ]
            ],
            'configuratorSettings' => [
                [
                    'id' => $redId,
                    'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ]
            ],
        ];

        $productEntity = $this->createTestProduct($productData);

        $router = $this->getContainer()->get('router');
        $requestContext = $router->getContext();

        $productUrl = $router->generate(
            'frontend.detail.page',
            ['productId' => $productEntity->getId()],
            RouterInterface::ABSOLUTE_URL
        );

        $productTag = new Keyword('Findologic Tag');
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

        $catUrl = $router->generate(
            'frontend.navigation.page',
            ['navigationId' => $productEntity->getCategories()->first()->getId()],
            RouterInterface::ABSOLUTE_PATH
        );

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

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->defaultContext)
            ->getElements();

        $userGroup = [];

        /** @var CustomerGroupEntity $customerGroupEntity */
        foreach ($customerGroupEntities as $customerGroupEntity) {
            $userGroup[] = new Usergroup(
                Utils::calculateUserGroupHash($this->shopkey, $customerGroupEntity->getId())
            );
        }

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

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct =
            $findologicProductFactory->buildInstance(
            $productEntity,
            $this->getContainer()->get('router'),
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
}
