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
use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Exceptions\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\RouterInterface;

class FindologicProductTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;
    use ConfigHelper;

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
        $this->shopkey = $this->getShopkey();
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
     *
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
        $productEntity->setTranslated(['name' => $name]);

        $findologicProductFactory = new FindologicProductFactory();

        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        if (!$exception) {
            $this->assertTrue($findologicProduct->hasName());
            $this->assertSame($name, $findologicProduct->getName());
        } else {
            $this->assertFalse($findologicProduct->hasName());
        }
    }

    /**
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testNoProductCategories(): void
    {
        $this->expectException(ProductHasNoCategoriesException::class);

        $productEntity = $this->createTestProduct();
        $productEntity->setCategories(new CategoryCollection([]));

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            [],
            new XMLItem('123')
        );
    }

    public function categorySeoProvider(): array
    {
        $categoryId = Uuid::randomHex();

        return [
            'Category does not have SEO path assigned' => [
                'data' => [
                    [
                        'id' => $categoryId,
                        'name' => 'FINDOLOGIC Category'
                    ]
                ],
                'categoryId' => $categoryId
            ],
            'Category have a pseudo empty SEO path assigned' => [
                'data' => [
                    [
                        'id' => $categoryId,
                        'name' => 'FINDOLOGIC Category',
                        'seoUrls' => [
                            [
                                'pathInfo' => 'navigation/' . $categoryId,
                                'seoPathInfo' => ' ',
                                'isCanonical' => true,
                                'routeName' => 'frontend.navigation.page'
                            ]
                        ]
                    ]
                ],
                'categoryId' => $categoryId
            ]
        ];
    }

    /**
     * @dataProvider categorySeoProvider
     *
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testProductCategoriesUrlWithoutSeoOrEmptyPath(array $data, string $categoryId): void
    {
        $categoryData['categories'] = $data;
        $productEntity = $this->createTestProduct($categoryData);

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $this->assertTrue($findologicProduct->hasAttributes());
        $attribute = current($findologicProduct->getAttributes());
        $this->assertSame('cat_url', $attribute->getKey());
        $this->assertSame(sprintf('/navigation/%s', $categoryId), current($attribute->getValues()));
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testProductCategoriesSeoUrl(): void
    {
        $productEntity = $this->createTestProduct();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $this->assertTrue($findologicProduct->hasAttributes());
        $attribute = current($findologicProduct->getAttributes());
        $this->assertSame('cat_url', $attribute->getKey());
        $this->assertSame('/Findologic-Category', current($attribute->getValues()));
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
     *
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

        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            [],
            new XMLItem('123')
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

        $productTag = new Keyword('FINDOLOGIC Tag');
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
            $customerGroupEntities,
            new XMLItem('123')
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

    public function ratingProvider(): array
    {
        $multipleRatings = [2.0, 4.0, 5.0, 1.0];
        $average = array_sum($multipleRatings) / count($multipleRatings);

        return [
            'No rating is provided' => ['ratings' => [], 'expectedRating' => 0.0],
            'Single rating is provided' => ['ratings' => [2.0], 'expectedRating' => 2.0],
            'Multiple ratings is provided' => ['ratings' => $multipleRatings, 'expectedRating' => $average]
        ];
    }

    /**
     * @dataProvider ratingProvider
     *
     * @param float[] $ratings
     *
     * @throws AccessEmptyPropertyException
     * @throws InconsistentCriteriaIdsException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testProductRatings(array $ratings, float $expectedRating): void
    {
        $productEntity = $this->createTestProduct();

        foreach ($ratings as $rating) {
            $reviewAId = Uuid::randomHex();
            $this->createProductReview($reviewAId, $rating, $productEntity->getId(), true);
        }

        $criteria = new Criteria([$productEntity->getId()]);
        $criteria = Utils::addProductAssociations($criteria);

        $productEntity = $this->getContainer()->get('product.repository')->search($criteria, $this->defaultContext)
            ->get($productEntity->getId());

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->defaultContext)
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $attributes = $findologicProduct->getAttributes();
        $ratingAttribute = end($attributes);
        $this->assertSame('rating', $ratingAttribute->getKey());
        $this->assertEquals($expectedRating, current($ratingAttribute->getValues()));
    }

    public function attributeProvider(): array
    {
        return [
            'filter with some special characters' => [
                'attributeName' => 'Special Characters /#+*()()=§(=\'\'!!"$.|',
                'expectedName' => 'SpecialCharacters'
            ],
            'filter with brackets' => [
                'attributeName' => 'Farbwiedergabe (Ra/CRI)',
                'expectedName' => 'FarbwiedergabeRaCRI'
            ],
            'filter with special UTF-8 characters' => [
                'attributeName' => 'Ausschnitt D ø (mm)',
                'expectedName' => 'AusschnittDmm'
            ],
            'filter dots and dashes' => [
                'attributeName' => 'free_shipping.. Really Cool--__',
                'expectedName' => 'free_shippingReallyCool--__'
            ],
            'filter with umlauts' => [
                'attributeName' => 'Umläüts äre cööl',
                'expectedName' => 'Umläütsärecööl'
            ],
        ];
    }

    /**
     * @dataProvider attributeProvider
     */
    public function testAttributesAreProperlyEscaped(string $attributeName, string $expectedName): void
    {
        $productEntity = $this->createTestProduct([
            'properties' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'some value',
                    'group' => [
                        'id' => Uuid::randomHex(),
                        'name' => $attributeName
                    ],
                ]
            ]
        ]);

        $criteria = new Criteria([$productEntity->getId()]);
        $criteria = Utils::addProductAssociations($criteria);

        $productEntity = $this->getContainer()->get('product.repository')->search($criteria, $this->defaultContext)
            ->get($productEntity->getId());

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->defaultContext)
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $foundAttributes = array_filter(
            $findologicProduct->getAttributes(),
            function (Attribute $attribute) use ($expectedName) {
                return $attribute->getKey() === $expectedName;
            }
        );

        /** @var Attribute $attribute */
        $attribute = reset($foundAttributes);
        $this->assertInstanceOf(
            Attribute::class,
            $attribute,
            sprintf('Attribute "%s" not present in attributes.', $expectedName)
        );
    }

    /**
     * @return Property[]
     */
    private function getProperties(ProductEntity $productEntity): array
    {
        $properties = [];

        if ($productEntity->getTax()) {
            $property = new Property('tax');
            $property->addValue((string)$productEntity->getTax()->getTaxRate());
            $properties[] = $property;
        }

        if ($productEntity->getDeliveryDate()->getLatest()) {
            $property = new Property('latestdeliverydate');
            $property->addValue($productEntity->getDeliveryDate()->getLatest()->format(DATE_ATOM));
            $properties[] = $property;
        }

        if ($productEntity->getDeliveryDate()->getEarliest()) {
            $property = new Property('earliestdeliverydate');
            $property->addValue($productEntity->getDeliveryDate()->getEarliest()->format(DATE_ATOM));
            $properties[] = $property;
        }

        if ($productEntity->getPurchaseUnit()) {
            $property = new Property('purchaseunit');
            $property->addValue((string)$productEntity->getPurchaseUnit());
            $properties[] = $property;
        }

        if ($productEntity->getReferenceUnit()) {
            $property = new Property('referenceunit');
            $property->addValue((string)$productEntity->getReferenceUnit());
            $properties[] = $property;
        }

        if ($productEntity->getPackUnit()) {
            $property = new Property('packunit');
            $property->addValue((string)$productEntity->getPackUnit());
            $properties[] = $property;
        }

        if ($productEntity->getStock()) {
            $property = new Property('stock');
            $property->addValue((string)$productEntity->getStock());
            $properties[] = $property;
        }

        if ($productEntity->getAvailableStock()) {
            $property = new Property('availableStock');
            $property->addValue((string)$productEntity->getAvailableStock());
            $properties[] = $property;
        }

        if ($productEntity->getWeight()) {
            $property = new Property('weight');
            $property->addValue((string)$productEntity->getWeight());
            $properties[] = $property;
        }

        if ($productEntity->getWidth()) {
            $property = new Property('width');
            $property->addValue((string)$productEntity->getWidth());
            $properties[] = $property;
        }

        if ($productEntity->getHeight()) {
            $property = new Property('height');
            $property->addValue((string)$productEntity->getHeight());
            $properties[] = $property;
        }

        if ($productEntity->getLength()) {
            $property = new Property('length');
            $property->addValue((string)$productEntity->getLength());
            $properties[] = $property;
        }

        if ($productEntity->getReleaseDate()) {
            $property = new Property('releasedate');
            $property->addValue((string)$productEntity->getReleaseDate()->format(DATE_ATOM));
            $properties[] = $property;
        }

        if ($productEntity->getManufacturer() && $productEntity->getManufacturer()->getMedia()) {
            $property = new Property('vendorlogo');
            $property->addValue($productEntity->getManufacturer()->getMedia()->getUrl());
            $properties[] = $property;
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
        $catUrl = '/Findologic-Category';
        $defaultCatUrl = sprintf('/navigation/%s', $productEntity->getCategories()->first()->getId());

        $attributes = [];
        $catUrlAttribute = new Attribute('cat_url', [$catUrl, $defaultCatUrl]);
        $vendorAttribute = new Attribute('vendor', ['FINDOLOGIC']);

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
        $rating = $productEntity->getRatingAverage() ?? 0.0;
        $attributes[] = new Attribute('rating', [$rating]);

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
