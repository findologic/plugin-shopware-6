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
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

use function array_map;
use function current;
use function explode;
use function getenv;
use function implode;
use function parse_url;

class FindologicProductTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;
    use ConfigHelper;
    use SalesChannelHelper;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var string */
    private $shopkey;

    /** @var RouterInterface */
    private $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = $this->getContainer()->get('router');
        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->shopkey = $this->getShopkey();

        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
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
        $productEntity->setTranslated(['name' => $name]);

        $findologicProductFactory = new FindologicProductFactory();

        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
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
            $this->shopkey,
            [],
            new XMLItem('123')
        );
    }

    public function categorySeoProvider(): array
    {
        $categoryId = Uuid::randomHex();

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $navigationCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        /** @var SalesChannelRepository $repos */
        $repos = $this->getContainer()->get('sales_channel.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Storefront'));

        $result = $repos->search($criteria, $salesChannelContext->getContext());
        /** @var SalesChannelEntity $additionalSalesChannel */
        $additionalSalesChannel = $result->first();
        $additionalSalesChannelId = $additionalSalesChannel->getId();

        return [
            'Category does not have SEO path assigned' => [
                'data' => [
                    [
                        'parentId' => $navigationCategoryId,
                        'id' => $categoryId,
                        'name' => 'FINDOLOGIC Category',
                        'seoUrls' => [
                            [
                                'id' => Uuid::randomHex(),
                                'salesChannelId' => Defaults::SALES_CHANNEL,
                                'pathInfo' => 'navigation/' . $categoryId,
                                'seoPathInfo' => 'Main',
                                'isCanonical' => true,
                                'routeName' => 'frontend.navigation.page'
                            ],
                            [
                                'id' => Uuid::randomHex(),
                                'salesChannelId' => $additionalSalesChannelId,
                                'pathInfo' => 'navigation/' . $categoryId,
                                'seoPathInfo' => 'Additional Main',
                                'isCanonical' => true,
                                'routeName' => 'frontend.navigation.page'
                            ]
                        ]
                    ]
                ],
                'categoryId' => $categoryId
            ],
            'Category have a pseudo empty SEO path assigned' => [
                'data' => [
                    [
                        'parentId' => $navigationCategoryId,
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
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $this->assertTrue($findologicProduct->hasAttributes());
        $attribute = current($findologicProduct->getAttributes());
        $this->assertSame('cat_url', $attribute->getKey());
        $this->assertNotContains('/Additional Main', $attribute->getValues());
        $this->assertContains(sprintf('/navigation/%s', $categoryId), $attribute->getValues());
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
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $this->assertTrue($findologicProduct->hasAttributes());
        $attribute = current($findologicProduct->getAttributes());
        $this->assertSame('cat_url', $attribute->getKey());
        $this->assertContains('/Findologic-Category', $attribute->getValues());
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

        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
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

        $productTag = new Keyword('FINDOLOGIC Tag');
        $images = $this->getImages($productEntity);
        $attributes = $this->getAttributes($productEntity);

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $userGroup = $this->getUserGroups($customerGroupEntities);
        $ordernumbers = $this->getOrdernumber($productEntity);
        $properties = $this->getProperties($productEntity);

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $salesChannel = $this->salesChannelContext->getSalesChannel();
        $domain = $salesChannel->getDomains()->first()->getUrl();

        $seoUrls = $productEntity->getSeoUrls()->filterBySalesChannelId($salesChannel->getId());
        $seoPath = $seoUrls->first()->getSeoPathInfo();
        $expectedUrl = sprintf('%s/%s', $domain, $seoPath);

        $this->assertEquals($expectedUrl, $findologicProduct->getUrl());
        $this->assertEquals($productEntity->getName(), $findologicProduct->getName());
        $this->assertEquals([$productTag], $findologicProduct->getKeywords());
        $this->assertEquals($images, $findologicProduct->getImages());
        $this->assertEquals(0, $findologicProduct->getSalesFrequency());
        $this->assertEqualsCanonicalizing($attributes, $findologicProduct->getAttributes());
        $this->assertEquals($userGroup, $findologicProduct->getUserGroups());
        $this->assertEquals($ordernumbers, $findologicProduct->getOrdernumbers());
        $this->assertEquals($properties, $findologicProduct->getProperties());
    }

    public function testProductWithCustomFields(): void
    {
        $data['customFields'] = ['findologic_size' => 100, 'findologic_color' => 'yellow'];
        $productEntity = $this->createTestProduct($data, true);

        $productFields = $productEntity->getCustomFields();
        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $attributes = $findologicProduct->getCustomFields();
        foreach ($attributes as $attribute) {
            $this->assertEquals($productFields[$attribute->getKey()], current($attribute->getValues()));
        }
    }

    public function testProductWithMultiSelectCustomFields(): void
    {
        $data['customFields'] = [
            'multi' => [
                'one value',
                'another value',
                'even a third one!'
            ]
        ];
        $productEntity = $this->createTestProduct($data, true);

        $productFields = $productEntity->getCustomFields();
        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $attributes = $findologicProduct->getCustomFields();
        foreach ($attributes as $attribute) {
            $this->assertEquals($productFields[$attribute->getKey()], $attribute->getValues());
        }
    }

    public function testProductWithLongCustomFieldValuesAreIgnored(): void
    {
        $data['customFields'] = ['long_value' => str_repeat('und wieder, ', 20000)];
        $productEntity = $this->createTestProduct($data, true);

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $this->assertEmpty($findologicProduct->getCustomFields());
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

        $productEntity = $this->getContainer()->get('product.repository')
            ->search($criteria, $this->salesChannelContext->getContext())
            ->get($productEntity->getId());

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
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
        $productEntity = $this->createTestProduct(
            [
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
            ]
        );

        $criteria = new Criteria([$productEntity->getId()]);
        $criteria = Utils::addProductAssociations($criteria);

        $productEntity = $this->getContainer()
            ->get('product.repository')
            ->search($criteria, $this->salesChannelContext->getContext())
            ->get($productEntity->getId());

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $foundAttributes = array_filter(
            $findologicProduct->getAttributes(),
            static function (Attribute $attribute) use ($expectedName) {
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

    public function testNonFilterablePropertiesAreExportedAsPropertiesInsteadOfAttributes(): void
    {
        if (Utils::versionLowerThan('6.2.0')) {
            $this->markTestSkipped('Properties can only have a filter visibility with Shopware 6.2.x and upwards');
        }

        $expectedPropertyName = 'blub';
        $expectedPropertyValue = 'some value';

        $productEntity = $this->createTestProduct(
            [
                'properties' => [
                    [
                        'id' => Uuid::randomHex(),
                        'name' => $expectedPropertyValue,
                        'group' => [
                            'id' => Uuid::randomHex(),
                            'name' => $expectedPropertyName,
                            'filterable' => false
                        ],
                    ]
                ]
            ]
        );

        $criteria = new Criteria([$productEntity->getId()]);
        $criteria = Utils::addProductAssociations($criteria);

        $productEntity = $this->getContainer()
            ->get('product.repository')
            ->search($criteria, $this->salesChannelContext->getContext())
            ->get($productEntity->getId());

        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $foundAttributes = array_filter(
            $findologicProduct->getAttributes(),
            static function (Attribute $attribute) use ($expectedPropertyName) {
                return $attribute->getKey() === $expectedPropertyName;
            }
        );

        $this->assertEmpty($foundAttributes);

        $foundProperties = array_filter(
            $findologicProduct->getProperties(),
            static function (Property $property) use ($expectedPropertyName) {
                return $property->getKey() === $expectedPropertyName;
            }
        );

        /** @var Property $property */
        $property = reset($foundProperties);
        $this->assertEquals($expectedPropertyValue, $property->getAllValues()['']); // '' = Empty usergroup.
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

        if ($productEntity->getPrice()) {
            $price = $productEntity->getPrice()->getCurrencyPrice($this->salesChannelContext->getCurrency()->getId());
            if ($price) {
                $listPrice = $price->getListPrice();
                if ($listPrice) {
                    $properties[] = new Property('old_price', [(string)$listPrice->getGross()]);
                    $properties[] = new Property('old_price_net', [(string)$listPrice->getNet()]);
                }
            }
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
        $catUrl1 = '/FINDOLOGIC-Category/';
        $catUrl2 = '/Findologic-Category';
        $defaultCatUrl = '';

        foreach ($productEntity->getCategories() as $category) {
            if ($category->getName() === 'FINDOLOGIC Category') {
                $defaultCatUrl = sprintf('/navigation/%s', $category->getId());
            }
        }

        $attributes = [];
        $catUrlAttribute = new Attribute('cat_url', [$catUrl1, $catUrl2, $defaultCatUrl]);
        $catAttribute = new Attribute('cat', ['FINDOLOGIC Category']);
        $vendorAttribute = new Attribute('vendor', ['FINDOLOGIC']);

        $attributes[] = $catUrlAttribute;
        $attributes[] = $catAttribute;
        $attributes[] = $vendorAttribute;

        $attributes[] = new Attribute(
            $productEntity->getProperties()
                ->first()
                ->getGroup()
                ->getName(),
            [
                $productEntity->getProperties()
                    ->first()
                    ->getName()
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

        $shippingFree = $this->translateBooleanValue($productEntity->getShippingFree());
        $attributes[] = new Attribute('shipping_free', [$shippingFree]);

        $rating = $productEntity->getRatingAverage() ?? 0.0;
        $attributes[] = new Attribute('rating', [$rating]);

        // Custom fields as attributes
        $productFields = $productEntity->getCustomFields();
        if ($productFields) {
            foreach ($productFields as $key => $value) {
                if (is_bool($value)) {
                    $value = $this->translateBooleanValue($value);
                }
                $attributes[] = new Attribute(Utils::removeSpecialChars($key), [$value]);
            }
        }

        foreach ($productEntity->getChildren() as $variant) {
            $productFields = $variant->getCustomFields();
            if ($productFields) {
                foreach ($productFields as $key => $value) {
                    if (is_bool($value)) {
                        $value = $this->translateBooleanValue($value);
                    }
                    $attributes[] = new Attribute(Utils::removeSpecialChars($key), [$value]);
                }
            }
        }

        return $attributes;
    }

    /**
     * @return Image[]
     */
    private function getImages(ProductEntity $productEntity): array
    {
        $images = [];
        if (!$productEntity->getMedia() || !$productEntity->getMedia()->count()) {
            $fallbackImage = sprintf(
                '%s/%s',
                getenv('APP_URL'),
                'bundles/storefront/assets/icon/default/placeholder.svg'
            );

            $images[] = new Image($fallbackImage);
            $images[] = new Image($fallbackImage, Image::TYPE_THUMBNAIL);

            return $images;
        }

        $mediaCollection = $productEntity->getMedia();
        $media = $mediaCollection->getMedia();
        $thumbnails = $media->first()->getThumbnails();

        $filteredThumbnails = $this->sortAndFilterThumbnailsByWidth($thumbnails);
        $firstThumbnail = $filteredThumbnails->first();

        $image = $firstThumbnail ?? $media->first();
        $url = $this->getEncodedUrl($image->getUrl());
        $images[] = new Image($url);

        foreach ($thumbnails as $thumbnail) {
            $url = $this->getEncodedUrl($thumbnail->getUrl());
            $images[] = new Image($url, Image::TYPE_THUMBNAIL);
        }

        return $images;
    }

    protected function getEncodedUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        $urlPath = explode('/', $parsedUrl['path']);
        $encodedPath = array_map('\FINDOLOGIC\FinSearch\Utils\Utils::multiByteRawUrlEncode', $urlPath);
        $parsedUrl['path'] = implode('/', $encodedPath);

        return Utils::buildUrl($parsedUrl);
    }

    public function emptyValuesProvider(): array
    {
        return [
            'null values provided' => ['value' => null],
            'empty string values provided' => ['value' => ''],
            'values containing empty spaces provided' => ['value' => '    '],
        ];
    }

    /**
     * @dataProvider emptyValuesProvider
     */
    public function testEmptyValuesAreSkipped(?string $value): void
    {
        $data = [
            'description' => $value,
            'referenceunit' => $value,
            'customFields' => [$value => 100, 'findologic_color' => $value]
        ];

        $productEntity = $this->createTestProduct($data);
        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $this->assertFalse($findologicProduct->hasDescription());
        $this->assertEmpty($findologicProduct->getCustomFields());
    }

    public function emptyAttributeNameProvider(): array
    {
        return [
            'Attribute name is null' => ['value' => null],
            'Attribute name is an empty string' => ['value' => ''],
            'Attribute name only contains empty spaces' => ['value' => '    '],
            'Attribute name only containing special characters' => ['value' => '$$$$%'],
        ];
    }

    /**
     * @dataProvider emptyAttributeNameProvider
     */
    public function testEmptyAttributeNamesAreSkipped(?string $value): void
    {
        $data = [
            'description' => 'Really interesting',
            'referenceunit' => 'cm',
            'customFields' => [$value => 'something']
        ];

        $productEntity = $this->createTestProduct($data);
        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $this->assertEmpty($findologicProduct->getCustomFields());
    }

    public function testCanonicalSeoUrlsAreUsedForTheConfiguredLanguage(): void
    {
        $this->markTestSkipped('Skipped until Shopware has fixed https://issues.shopware.com/issues/NEXT-11429');
        $defaultContext = Context::createDefaultContext();

        /** @var EntityRepository $salesChannelRepo */
        $salesChannelRepo = $this->getContainer()->get('sales_channel.repository');
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $salesChannelRepo->search(new Criteria(), Context::createDefaultContext())->last();

        /** @var EntityRepository $localeRepo */
        $localeRepo = $this->getContainer()->get('language.repository');
        /** @var LanguageEntity $language */
        $language = $localeRepo->search(new Criteria(), Context::createDefaultContext())->first();

        $defaultLanguageId = $this->salesChannelContext->getSalesChannel()->getLanguageId();

        $seoUrlRepo = $this->getContainer()->get('seo_url.repository');
        $firstSeoUrlId = Uuid::randomHex();
        $lastSeoUrlId = Uuid::randomHex();

        $seoUrlRepo->upsert(
            [
                [
                    'id' => $firstSeoUrlId,
                    'pathInfo' => '/detail/' . Uuid::randomHex(),
                    'seoPathInfo' => 'I-Should-Be-Used/Because/Used/Language',
                    'isCanonical' => true,
                    'routeName' => 'frontend.detail.page',
                    'languageId' => $language->getId(),
                    'salesChannelId' => $salesChannel->getId()
                ],
                [
                    'id' => $lastSeoUrlId,
                    'pathInfo' => '/detail/' . Uuid::randomHex(),
                    'seoPathInfo' => 'I-Should-Not-Be-Used/Because/Wrong/Language',
                    'isCanonical' => true,
                    'routeName' => 'frontend.detail.page',
                    'languageId' => $defaultLanguageId,
                    'salesChannelId' => $salesChannel->getId()
                ]
            ],
            $defaultContext
        );

        $productEntity = $this->createTestProduct();

        // Manually delete all seo URLs from the product, and manually assign SEO URLs to product,
        // to prevent collision in case of a race condition. We need to sleep here, since the product created event
        // is asynchronous and runs in another thread.
        // See https://issues.shopware.com/issues/NEXT-11429.
        sleep(5);
        $seoUrls = array_values(
            array_map(
                function ($id) {
                    return ['id' => $id];
                },
                $productEntity->getSeoUrls()->getIds()
            )
        );
        $seoUrlRepo->delete($seoUrls, $defaultContext);

        $productRepo = $this->getContainer()->get('product.repository');
        $productRepo->update(
            [
                [
                    'id' => $productEntity->getId(),
                    'seoUrls' => [
                        ['id' => $firstSeoUrlId],
                        ['id' => $lastSeoUrlId]
                    ]
                ]
            ],
            $defaultContext
        );

        $salesChannelRepo = $this->getContainer()->get('sales_channel.repository');
        $storeFrontSalesChannel = $salesChannelRepo->search(new Criteria(), Context::createDefaultContext())->last();
        $salesChannelContext = $this->buildSalesChannelContext($storeFrontSalesChannel->getId(), 'https://blub.io');
        $this->getContainer()->set('fin_search.sales_channel_context', $salesChannelContext);
        $salesChannel = $salesChannelContext->getSalesChannel();

        // Manually sort the correct SEO URL below all other SEO URLs, to ensure the SEO URL is not correct, because
        // it is the first one in the database, but that the proper translation matches instead.
        $productEntity->getSeoUrls()->sort(
            function (SeoUrlEntity $seoUrlEntity) {
                return $seoUrlEntity->getSeoPathInfo() === 'I-Should-Be-Used/Because/Used/Language' ? -1 : 1;
            }
        );

        /** @var SalesChannelDomainEntity $domainEntity */
        $domainEntity = $salesChannel->getDomains()->filter(
            function (SalesChannelDomainEntity $domain) use ($salesChannel) {
                return $domain->getLanguageId() === $salesChannel->getLanguageId();
            }
        )->first();
        $seoUrls = $productEntity->getSeoUrls()->filterBySalesChannelId($salesChannel->getId());
        /** @var SeoUrlEntity $seoUrlEntity */
        $seoUrlEntity = $seoUrls->filter(
            function (SeoUrlEntity $seoUrl) use ($salesChannel) {
                return $seoUrl->getLanguageId() === $salesChannel->getLanguageId();
            }
        )->first();

        $expectedUrl = sprintf('%s/%s', $domainEntity->getUrl(), $seoUrlEntity->getSeoPathInfo());

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $this->assertEquals($expectedUrl, $findologicProduct->getUrl());
    }

    public function testEmptyCategoryNameShouldStillExportCategory(): void
    {
        $mainCatId = $this->getContainer()->get('category.repository')
            ->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        $categoryId = Uuid::randomHex();
        $pathInfo = 'navigation/' . $categoryId;
        $seoPathInfo = 'Findologic-Category';

        $expectedFirstCatUrl = '/' . $seoPathInfo;
        $expectedSecondCatUrl = '/' . $pathInfo;

        $productEntity = $this->createTestProduct(
            [
                'categories' => [
                    [
                        'parentId' => $mainCatId,
                        'id' => $categoryId,
                        'name' => ' ',
                        'seoUrls' => [
                            [
                                'pathInfo' => $pathInfo,
                                'seoPathInfo' => $seoPathInfo,
                                'isCanonical' => true,
                                'routeName' => 'frontend.navigation.page',
                            ]
                        ],
                    ],
                ],
            ]
        );

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $this->assertCount(6, $findologicProduct->getAttributes());
        $this->assertSame('cat_url', $findologicProduct->getAttributes()[0]->getKey());

        $catUrls = $findologicProduct->getAttributes()[0]->getValues();
        $this->assertCount(2, $catUrls);

        $this->assertSame($expectedFirstCatUrl, $catUrls[0]);
        $this->assertSame($expectedSecondCatUrl, $catUrls[1]);
    }

    public function testCustomerGroupsAreExportedAsUserGroups(): void
    {
        $context = Context::createDefaultContext();

        $customerGroupRepo = $this->getContainer()->get('customer_group.repository');
        $customerGroupRepo->upsert(
            [
                [
                    'name' => 'Net customer group',
                    'displayGross' => false
                ],
                [
                    'name' => 'Gross customer group',
                    'displayGross' => true
                ]
            ],
            $context
        );

        $customerGroups = $customerGroupRepo->search(new Criteria(), $context);
        // Manually sort customer group entities for asserting, since otherwise they would be sorted randomly.
        $customerGroups->sort(
            function (CustomerGroupEntity $a, CustomerGroupEntity $b) {
                if ($a->getDisplayGross() && !$b->getDisplayGross()) {
                    return 1;
                }

                if ($b->getDisplayGross() && !$a->getDisplayGross()) {
                    return -1;
                }

                return 0;
            }
        );

        $productEntity = $this->createTestProduct();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroups->getElements(),
            new XMLItem('123')
        );

        $customerGroupElements = array_values($customerGroups->getElements());
        $standardCustomerGroup = Utils::calculateUserGroupHash($this->shopkey, $customerGroupElements[0]->getId());
        $netCustomerGroup = Utils::calculateUserGroupHash(
            $this->shopkey,
            $customerGroupElements[1]->getId()
        );
        $grossCustomerGroup = Utils::calculateUserGroupHash(
            $this->shopkey,
            $customerGroupElements[2]->getId()
        );

        $actualPrices = $findologicProduct->getPrices();

        $this->assertCount(4, $actualPrices);
        $this->assertEquals($this->buildXmlPrice(10, $standardCustomerGroup), $actualPrices[0]);
        $this->assertEquals($this->buildXmlPrice(15, $netCustomerGroup), $actualPrices[1]);
        $this->assertEquals($this->buildXmlPrice(15, $grossCustomerGroup), $actualPrices[2]);
        $this->assertEquals($this->buildXmlPrice(15), $actualPrices[3]);
    }

    public function testUsesMemoryEfficientWayToFetchSalesFrequency(): void
    {
        $expectedSalesFrequency = 1337;

        $productEntity = $this->createTestProduct();

        $containerMock = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderLineItemRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $searchResultMock = $this->getMockBuilder(IdSearchResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Ensure only memory efficient calls are being made.
        $orderLineItemRepositoryMock->expects($this->once())->method('searchIds')
            ->willReturn($searchResultMock);
        $orderLineItemRepositoryMock->expects($this->never())->method('search');
        $searchResultMock->expects($this->once())->method('getTotal')->willReturn($expectedSalesFrequency);

        $containerMock->expects($this->any())->method('get')->willReturnCallback(
            function (string $name) use ($orderLineItemRepositoryMock) {
                if ($name === 'order_line_item.repository') {
                    return $orderLineItemRepositoryMock;
                }

                return $this->getContainer()->get($name);
            }
        );

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $containerMock,
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $this->assertSame($expectedSalesFrequency, $findologicProduct->getSalesFrequency());
    }

    private function buildXmlPrice(float $value, string $userGroup = ''): Price
    {
        $price = new Price();
        $price->setValue($value, $userGroup);

        return $price;
    }

    private function translateBooleanValue(bool $value): string
    {
        $translationKey = $value ? 'finSearch.general.yes' : 'finSearch.general.no';

        return $this->getContainer()->get('translator')->trans($translationKey);
    }

    public function listPriceProvider(): array
    {
        return [
            'List price is available for the sales channel currency' => [
                'currencyId' => Defaults::CURRENCY,
                'isPriceAvailable' => true
            ],
            'List price is available for a different currency' => [
                'currencyId' => null,
                'isPriceAvailable' => false
            ]
        ];
    }

    /**
     * @dataProvider listPriceProvider
     */
    public function testProductListPrice(?string $currencyId, bool $isPriceAvailable): void
    {
        if ($currencyId === null) {
            $currencyId = $this->createCurrency();
        }

        $productEntity = $this->createTestProduct(
            [
                'price' => [
                    [
                        'currencyId' => $currencyId,
                        'gross' => 50,
                        'net' => 40,
                        'linked' => false,
                        'listPrice' => [
                            'net' => 20,
                            'gross' => 25,
                            'linked' => false
                        ]
                    ]
                ],
            ]
        );

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $hasListPrice = false;
        $hasListPriceNet = false;

        foreach ($findologicProduct->getProperties() as $property) {
            if ($property->getKey() === 'old_price') {
                $hasListPrice = true;
                $this->assertEquals(25, current($property->getAllValues()));
            }
            if ($property->getKey() === 'old_price_net') {
                $hasListPriceNet = true;
                $this->assertEquals(20, current($property->getAllValues()));
            }
        }

        $this->assertSame($isPriceAvailable, $hasListPrice);
        $this->assertSame($isPriceAvailable, $hasListPriceNet);
    }

    private function createCurrency(): string
    {
        $currencyId = Uuid::randomHex();
        /** @var EntityRepositoryInterface $currencyRepo */
        $currencyRepo = $this->getContainer()->get('currency.repository');
        $currencyRepo->upsert(
            [
                [
                    'id' => $currencyId,
                    'isoCode' => 'FDL',
                    'factor' => 1,
                    'symbol' => 'F',
                    'decimalPrecision' => 2,
                    'name' => 'Findologic Currency',
                    'shortName' => 'FL'
                ]
            ],
            $this->salesChannelContext->getContext()
        );

        return $currencyId;
    }

    public function testCatUrlsContainDomainPathAsPrefix(): void
    {
        $expectedPath = '/staging/public';
        $fullDomain = 'http://test.de' . $expectedPath;
        $domainRepo = $this->getContainer()->get('sales_channel_domain.repository');
        $catUrlWithoutSeoUrlPrefix = '/navigation';

        $domainRepo->create([
            [
                'url' => $fullDomain,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'currencyId' => Defaults::CURRENCY,
                'snippetSet' => [
                    'name' => 'oof',
                    'baseFile' => 'de.json',
                    'iso' => 'de_AT'
                ]
            ]
        ], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('url', $fullDomain));

        // Wait until the domain entity has been created, since DAL works with events and these events
        // run asynchronously on a different thread.
        do {
            $result = $domainRepo->search($criteria, Context::createDefaultContext());
        } while ($result->getTotal() <= 0);

        // The sales channel should use the newly generated URL instead of the default domain.
        $this->salesChannelContext->getSalesChannel()->setLanguageId($result->getEntities()->first()->getLanguageId());
        $this->salesChannelContext->getSalesChannel()->setDomains($result->getEntities());

        $productEntity = $this->createTestProduct();
        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $attributes = $findologicProduct->getAttributes();
        $hasSeoCatUrls = false;
        foreach ($attributes as $attribute) {
            if ($attribute->getKey() === 'cat_url') {
                foreach ($attribute->getValues() as $value) {
                    // We only care about SEO URLs of categories. Non-SEO categories are automatically generated
                    // by the Shopware router.
                    if (!(strpos($value, $catUrlWithoutSeoUrlPrefix) === 0)) {
                        $hasSeoCatUrls = true;
                        $this->assertStringStartsWith($expectedPath, $value);
                    }
                }
            }
        }

        $this->assertTrue($hasSeoCatUrls);
    }

    public function thumbnailProvider(): array
    {
        return [
            '3 thumbnails 400x400, 600x600 and 1000x100, the image of width 600 is taken' => [
                'thumbnails' => [
                    [
                        'width' => 400,
                        'height' => 400,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/400'
                    ],
                    [
                        'width' => 600,
                        'height' => 600,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/600'
                    ],
                    [
                        'width' => 1000,
                        'height' => 100,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/100'
                    ]
                ]
            ],
            '2 thumbnails 800x800 and 2000x200, the image of width 800 is taken' => [
                'thumbnails' => [
                    [
                        'width' => 800,
                        'height' => 800,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/800'
                    ],
                    [
                        'width' => 2000,
                        'height' => 200,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/200'
                    ]
                ]
            ],
            '3 thumbnails 100x100, 200x200 and 400x400, the image directly assigned to the product is taken' => [
                'thumbnails' => [
                    [
                        'width' => 100,
                        'height' => 100,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/100'
                    ],
                    [
                        'width' => 200,
                        'height' => 200,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/200'
                    ],
                    [
                        'width' => 400,
                        'height' => 400,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/400'
                    ]
                ]
            ],
            '0 thumbnails, the image directly assigned to the product is taken' => ['thumbnails' => []]
        ];
    }

    /**
     * @dataProvider thumbnailProvider
     */
    public function testCorrectThumbnailImageIsExported(array $thumbnails): void
    {
        $productEntity = $this->createTestProduct(['cover' => ['media' => ['thumbnails' => $thumbnails]]], false, true);
        $images = $this->getImages($productEntity);
        $customerGroupEntities = $this->getContainer()
            ->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct = $findologicProductFactory->buildInstance(
            $productEntity,
            $this->router,
            $this->getContainer(),
            $this->shopkey,
            $customerGroupEntities,
            new XMLItem('123')
        );

        $findologicImages = $findologicProduct->getImages();
        $this->assertEquals($images, $findologicImages);
    }

    private function sortAndFilterThumbnailsByWidth(MediaThumbnailCollection $thumbnails): MediaThumbnailCollection
    {
        $filteredThumbnails = $thumbnails->filter(static function ($thumbnail) {
            return $thumbnail->getWidth() >= 600;
        });

        $filteredThumbnails->sort(function (MediaThumbnailEntity $a, MediaThumbnailEntity $b) {
            return $a->getWidth() <=> $b->getWidth();
        });

        return $filteredThumbnails;
    }

    public function widthSizesProvider()
    {
        return [
            'Max 600 width is provided' => [
                'widthSizes' => [100, 200, 300, 400, 500, 600],
                'expected' => [600]
            ],
            'Min 600 width is provided' => [
                'widthSizes' => [600, 800, 200, 500],
                'expected' => [600, 800]
            ],
            'Random width are provided' => [
                'widthSizes' => [800, 100, 650, 120, 2000, 1000],
                'expected' => [650, 800, 1000, 2000]
            ],
            'Less than 600 width is provided' => [
                'widthSizes' => [100, 200, 300, 500],
                'expected' => []
            ]
        ];
    }

    /**
     * @dataProvider widthSizesProvider
     */
    public function testImageThumbnailsAreFilteredAndSortedByWidth(array $widthSizes, array $expected): void
    {
        $thumbnails = $this->generateThumbnailData($widthSizes);
        $productEntity = $this->createTestProduct(['cover' => ['media' => ['thumbnails' => $thumbnails]]], false, true);
        $mediaCollection = $productEntity->getMedia();
        $media = $mediaCollection->getMedia();
        $thumbnailCollection = $media->first()->getThumbnails();

        $width = [];
        $filteredThumbnails = $this->sortAndFilterThumbnailsByWidth($thumbnailCollection);
        foreach ($filteredThumbnails as $filteredThumbnail) {
            $width[] = $filteredThumbnail->getWidth();
        }

        $this->assertSame($expected, $width);
    }

    private function generateThumbnailData(array $sizes): array
    {
        $thumbnails = [];
        foreach ($sizes as $width) {
            $thumbnails[] = [
                'width' => $width,
                'height' => 100,
                'highDpi' => false,
                'url' => 'https://via.placeholder.com/100'
            ];
        }

        return $thumbnails;
    }
}
