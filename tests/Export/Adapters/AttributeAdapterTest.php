<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\Adapters\AttributeAdapter;
use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\AttributeHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ExportItemAdapterHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class AttributeAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use AttributeHelper;
    use ConfigHelper;
    use ExportItemAdapterHelper;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var AttributeAdapter */
    protected $attributeAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
        DynamicProductGroupService::getInstance(
            $this->getContainer(),
            $this->getContainer()->get('serializer.mapping.cache.symfony'),
            Context::createDefaultContext(),
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            0
        );
        $this->getContainer()->set(
            'fin_search.export_context',
            new ExportContext(
                'ABCDABCDABCDABCDABCDABCDABCDABCD',
                [],
                $this->getCategory()
            )
        );

        $this->attributeAdapter = $this->getContainer()->get(AttributeAdapter::class);
    }

    public function testAttributeContainsAttributeOfTheProduct(): void
    {
        $id = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $variantProductNumber = Uuid::randomHex();

        $product = $this->createTestProduct([
            'id' => $id
        ]);

        $variantProduct = $this->createTestProduct([
            'id' => $variantId,
            'parentId' => $id,
            'productNumber' => $variantProductNumber,
            'shippingFree' => false
        ]);

        $expected = array_merge(
            $this->getAttributes($product, IntegrationType::API),
            $this->getAttributes($variantProduct, IntegrationType::API)
        );

        $attributes = array_merge(
            $this->attributeAdapter->adapt($product),
            $this->attributeAdapter->adapt($variantProduct)
        );

        $this->assertEquals($expected, $attributes);
    }

    /**
     * @dataProvider attributeProvider
     */
    public function testAttributesAreProperlyEscaped(
        string $integrationType,
        string $attributeName,
        string $expectedName
    ): void {
        $config = $this->getMockedConfig($integrationType);

        $adapter = $this->getAttributeAdapter($config);

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

        $attributes = $adapter->adapt($productEntity);

        $foundAttributes = array_filter(
            $attributes,
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

    /**
     * @dataProvider multiSelectCustomFieldsProvider
     */
    public function testProductWithMultiSelectCustomFields(
        array $customFields,
        array $expectedCustomFieldAttributes
    ): void {
        $data['customFields'] = $customFields;
        $productEntity = $this->createTestProduct($data, true);
        $attributes = $this->attributeAdapter->adapt($productEntity);

        foreach ($attributes as $attribute) {
            if ($attribute->getKey() !== 'multi') {
                continue;
            }

            $this->assertEquals($expectedCustomFieldAttributes[$attribute->getKey()], $attribute->getValues());
        }
    }

    public function testProductWithLongCustomFieldValuesAreIgnored(): void
    {
        $data['customFields'] = ['long_value' => str_repeat('und wieder, ', 20000)];
        $productEntity = $this->createTestProduct($data, true);
        $attributes = $this->attributeAdapter->adapt($productEntity);
        $customFieldAttributes = $this->getCustomFields($attributes, $data);

        $this->assertEmpty($customFieldAttributes);
    }

    /**
     * @dataProvider ratingProvider
     *
     * @param float[] $ratings
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

        $attributes = $this->attributeAdapter->adapt($productEntity);

        $ratingAttribute = end($attributes);
        $this->assertSame('rating', $ratingAttribute->getKey());
        $this->assertEquals($expectedRating, current($ratingAttribute->getValues()));
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
        $config = $this->getMockedConfig('API');
        $adapter = $this->getAttributeAdapter($config);
        $attributes = $adapter->adapt($productEntity);
        $customFieldAttributes = $this->getCustomFields($attributes, $data);

        $this->assertEmpty($customFieldAttributes);
    }

    /**
     * @dataProvider categoryAndCatUrlWithIntegrationTypeProvider
     */
    public function testCategoryAndCatUrlExportBasedOnIntegrationType(
        ?string $integrationType,
        array $categories,
        array $expectedCategories,
        array $expectedCatUrls
    ): void {
        foreach ($categories as $key => $category) {
            $navigationCategoryId = $this->salesChannelContext->getSalesChannel()->getNavigationCategoryId();
            $categories[$key]['parentId'] = $navigationCategoryId;
        }

        $productEntity = $this->createTestProduct(['categories' => $categories]);
        $config = $this->getMockedConfig($integrationType);
        $adapter = $this->getAttributeAdapter($config);
        $attributes = $adapter->adapt($productEntity);

        if (count($expectedCatUrls) > 0) {
            $this->assertSame('cat_url', $attributes[0]->getKey());
            $this->assertSameSize($expectedCatUrls, $attributes[0]->getValues());
            $this->assertSame($expectedCatUrls, $attributes[0]->getValues());

            $this->assertSame('cat', $attributes[1]->getKey());
            $this->assertSameSize($expectedCategories, $attributes[1]->getValues());
            $this->assertSame($expectedCategories, $attributes[1]->getValues());
        } else {
            $this->assertSame('cat', $attributes[0]->getKey());
            $this->assertSameSize($expectedCategories, $attributes[0]->getValues());
            $this->assertSame($expectedCategories, $attributes[0]->getValues());
        }
    }

    public function testAttributesAreHtmlEntityEncoded(): void
    {
        $expectedAttributeValue = '>80';
        $data['customFields'] = ['length' => '&gt;80'];

        $productEntity = $this->createTestProduct($data, true);

        $attributes = $this->attributeAdapter->adapt($productEntity);
        $relatedAttributes = $this->getCustomFields($attributes, $data);

        $this->assertCount(1, $relatedAttributes);
        $this->assertSame($expectedAttributeValue, $relatedAttributes[0]->getValues()[0]);
    }

    public function testProductWithCustomFields(): void
    {
        $data = [
            'customFields' => [
                'findologic_size' => 100,
                'findologic_color' => 'yellow'
            ]
        ];
        $productEntity = $this->createTestProduct($data, true);
        $productFields = $productEntity->getCustomFields();
        $attributes = $this->attributeAdapter->adapt($productEntity);
        $customAttributes = $this->getCustomFields($attributes, $data);

        $this->assertCount(2, $customAttributes);
        foreach ($customAttributes as $attribute) {
            $this->assertEquals($productFields[$attribute->getKey()], current($attribute->getValues()));
        }
    }

    public function testMultiDimensionalCustomFieldsAreIgnored(): void
    {
        $data = [
            'customFields' => [
                'multidimensional' => [
                    ['interesting' => 'this is some multidimensional data wow!']
                ]
            ]
        ];
        $productEntity = $this->createTestProduct($data, true);
        $attributes = $this->attributeAdapter->adapt($productEntity);
        $customAttributes = $this->getCustomFields($attributes, $data);

        $this->assertEmpty($customAttributes);
    }

    public function testCustomFieldsContainingZeroAsStringAreProperlyExported(): void
    {
        $data['customFields'] = ['nice' => "0\n"];
        $productEntity = $this->createTestProduct($data, true);

        $attributes = $this->attributeAdapter->adapt($productEntity);
        $customAttributes = $this->getCustomFields($attributes, $data);

        $this->assertCount(1, $customAttributes);
        $this->assertSame(['0'], $customAttributes[0]->getValues());
    }

    /**
     * @dataProvider emptyValuesProvider
     */
    public function testEmptyAttributeValuesAreSkipped(?string $value): void
    {
        $data = [
            'customFields' => [$value => 100, 'findologic_color' => $value]
        ];

        $productEntity = $this->createTestProduct($data);
        $attributes = $this->attributeAdapter->adapt($productEntity);
        $customAttributes = $this->getCustomFields($attributes, $data);

        $this->assertEmpty($customAttributes);
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

        $config = $this->getMockedConfig();
        $adapter = $this->getAttributeAdapter($config);
        $attributes = $adapter->adapt($productEntity);

        $attribute = current($attributes);
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
        $config = $this->getMockedConfig();
        $adapter = $this->getAttributeAdapter($config);
        $attributes = $adapter->adapt($productEntity);

        $attribute = current($attributes);
        $this->assertSame('cat_url', $attribute->getKey());
        $this->assertContains('/FINDOLOGIC-Category/', $attribute->getValues());
    }

    public function testEmptyCategoryNameShouldStillExportCategory(): void
    {
        $mainCatId = $this->getContainer()->get('category.repository')
            ->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        $categoryId = Uuid::randomHex();
        $pathInfo = 'navigation/' . $categoryId;
        $seoPathInfo = '/FINDOLOGIC-Category/';
        $expectedCatUrl = '/' . $pathInfo;

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

        $config = $this->getMockedConfig();
        $adapter = $this->getAttributeAdapter($config);
        $attributes = $adapter->adapt($productEntity);

        $this->assertCount(6, $attributes);
        $this->assertSame('cat_url', $attributes[0]->getKey());

        $catUrls = $attributes[0]->getValues();
        $this->assertCount(1, $catUrls);
        $this->assertSame([$expectedCatUrl], $catUrls);
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

        $config = $this->getMockedConfig();
        $adapter = $this->getAttributeAdapter($config);
        $attributes = $adapter->adapt($productEntity);

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

    public function testProductAndVariantHaveNoCategories(): void
    {
        $this->expectException(ProductHasNoCategoriesException::class);
        $id = Uuid::randomHex();
        $this->createTestProduct([
            'id' => $id,
            'categories' => []
        ]);

        $this->createTestProduct([
            'parentId' => $id,
            'productNumber' => Uuid::randomHex(),
            'categories' => []
        ]);

        $criteria = new Criteria([$id]);
        $criteria = Utils::addProductAssociations($criteria);
        $criteria->addAssociation('visibilities');
        $productEntity = $this->getContainer()->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        )->get($id);

        $this->attributeAdapter->adapt($productEntity);
    }

    public function parentAndChildrenCategoryProvider(): array
    {
        return [
            'Parent and children have the same categories assigned' => [
                'isParentAssigned' => true,
                'isVariantAssigned' => true,
            ],
            'Parent has no categories and children have some categories assigned' => [
                'isParentAssigned' => false,
                'isVariantAssigned' => true
            ],
            'Parent has categories and children have no categories assigned' => [
                'isParentAssigned' => true,
                'isVariantAssigned' => false
            ]
        ];
    }

    /**
     * @dataProvider parentAndChildrenCategoryProvider
     */
    public function testOnlyUniqueCategoriesAreExported(bool $isParentAssigned, bool $isVariantAssigned): void
    {
        $id = Uuid::randomHex();
        $mainNavigationCategoryId = $this->salesChannelContext->getSalesChannel()->getNavigationCategoryId();
        $categoryOne = [
            'id' => 'cce80a72bc3481d723c38cccf592d45a',
            'name' => 'Category1',
            'parentId' => $mainNavigationCategoryId
        ];

        $expectedCategories = ['Category1'];
        $expectedCatUrls = [
            '/Category1/',
            '/navigation/cce80a72bc3481d723c38cccf592d45a'
        ];

        $productEntity = $this->createTestProduct([
            'id' => $id,
            'categories' => $isParentAssigned ? [$categoryOne] : []
        ]);

        $childEntity = $this->createTestProduct([
            'parentId' => $id,
            'productNumber' => Uuid::randomHex(),
            'categories' => $isVariantAssigned ? [$categoryOne] : [],
            'shippingFree' => false
        ]);

        $config = $this->getMockedConfig();
        $initialItem = new XMLItem('123');
        $exportItemAdapter = $this->getExportItemAdapter($config);

        $item = $exportItemAdapter->adapt($initialItem, $productEntity);

        if ($item === null) {
            $item = $initialItem;
        }

        $exportItemAdapter->adaptVariant($item, $childEntity);
        $reflector = new ReflectionClass($item);
        $attributes = $reflector->getProperty('attributes');
        $attributes->setAccessible(true);
        $value = $attributes->getValue($item);

        $this->assertArrayHasKey('cat_url', $value);
        $categoryUrlAttributeValues = $value['cat_url']->getValues();
        $this->assertSame($expectedCatUrls, $categoryUrlAttributeValues);

        $this->assertArrayHasKey('cat', $value);
        $categoryAttributeValues = $value['cat']->getValues();
        $this->assertSame($expectedCategories, $categoryAttributeValues);
    }

    private function getMockedConfig(string $integrationType = 'Direct Integration'): Config
    {
        $override = [
            'languageId' => $this->salesChannelContext->getSalesChannel()->getLanguageId(),
            'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId()
        ];

        return $this->getFindologicConfig($override, $integrationType === 'Direct Integration');
    }

    private function getAttributeAdapter(Config $config): AttributeAdapter
    {
        return new AttributeAdapter(
            $this->getContainer(),
            $config,
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get('fin_search.sales_channel_context'),
            $this->getContainer()->get(UrlBuilderService::class),
            $this->getContainer()->get('fin_search.export_context')
        );
    }

    /**
     * @param Attribute[] $attributes
     * @param array<string, string|array> $customFields
     * @return array
     */
    public function getCustomFields(array $attributes, array $customFields): array
    {
        $customFieldAttributes = [];

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute->getKey(), $customFields['customFields'])) {
                $customFieldAttributes[] = $attribute;
            }
        }

        return $customFieldAttributes;
    }

    public function categorySeoProvider(): array
    {
        $categoryId = Uuid::randomHex();

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $navigationCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        $repos = $this->getContainer()->get('sales_channel.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

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


    public function attributeProvider(): array
    {
        return [
            'API Integration filter with some special characters' => [
                'integrationType' => 'API',
                'attributeName' => 'Special Characters /#+*()()=§(=\'\'!!"$.|',
                'expectedName' => 'SpecialCharacters'
            ],
            'API Integration filter with brackets' => [
                'integrationType' => 'API',
                'attributeName' => 'Farbwiedergabe (Ra/CRI)',
                'expectedName' => 'FarbwiedergabeRaCRI'
            ],
            'API Integration filter with special UTF-8 characters' => [
                'integrationType' => 'API',
                'attributeName' => 'Ausschnitt D ø (mm)',
                'expectedName' => 'AusschnittDmm'
            ],
            'API Integration filter dots and dashes' => [
                'integrationType' => 'API',
                'attributeName' => 'free_shipping.. Really Cool--__',
                'expectedName' => 'free_shippingReallyCool--__'
            ],
            'API Integration filter with umlauts' => [
                'integrationType' => 'API',
                'attributeName' => 'Umläüts äre cööl',
                'expectedName' => 'Umläütsärecööl'
            ],
            'Direct Integration filter with some special characters' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'Special Characters /#+*()()=§(=\'\'!!"$.|',
                'expectedName' => 'Special Characters /#+*()()=§(=\'\'!!"$.|'
            ],
            'Direct Integration filter with brackets' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'Farbwiedergabe (Ra/CRI)',
                'expectedName' => 'Farbwiedergabe (Ra/CRI)'
            ],
            'Direct Integration filter with special UTF-8 characters' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'Ausschnitt D ø (mm)',
                'expectedName' => 'Ausschnitt D ø (mm)'
            ],
            'Direct Integration filter dots and dashes' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'free_shipping.. Really Cool--__',
                'expectedName' => 'free_shipping.. Really Cool--__'
            ],
            'Direct Integration filter with umlauts' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'Umläüts äre cööl',
                'expectedName' => 'Umläüts äre cööl'
            ],
        ];
    }

    public function multiSelectCustomFieldsProvider(): array
    {
        return [
            'multiple values' => [
                'customFields' => [
                    'multi' => [
                        'one value',
                        'another value',
                        'even a third one!'
                    ],
                ],
                'expectedCustomFieldAttributes' => [
                    'multi' => [
                        'one value',
                        'another value',
                        'even a third one!'
                    ],
                ],
            ],
            'multiple values with one null value' => [
                'customFields' => [
                    'multiWithNull' => [
                        'one value',
                        'another value',
                        'even a third one!',
                        null
                    ],
                ],
                'expectedCustomFieldAttributes' => [
                    'multiWithNull' => [
                        'one value',
                        'another value',
                        'even a third one!'
                    ],
                ],
            ],
            'multiple values with one empty value' => [
                'customFields' => [
                    'multiWithEmptyValue' => [
                        'one value',
                        'another value',
                        'even a third one!',
                        ''
                    ],
                ],
                'expectedCustomFieldAttributes' => [
                    'multiWithEmptyValue' => [
                        'one value',
                        'another value',
                        'even a third one!'
                    ],
                ],
            ]
        ];
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

    public function emptyAttributeNameProvider(): array
    {
        return [
            'Attribute name is null' => ['value' => null],
            'Attribute name is an empty string' => ['value' => ''],
            'Attribute name only contains empty spaces' => ['value' => '    '],
            'Attribute name only containing special characters' => ['value' => '$$$$%'],
        ];
    }

    public function emptyValuesProvider(): array
    {
        return [
            'null values provided' => ['value' => null],
            'empty string values provided' => ['value' => ''],
            'values containing empty spaces provided' => ['value' => '    '],
        ];
    }

    public function categoryAndCatUrlWithIntegrationTypeProvider(): array
    {
        return [
            'Integration type is API and category is at first level' => [
                'integrationType' => 'API',
                'categories' => [
                    [
                        'id' => 'cce80a72bc3481d723c38cccf592d45a',
                        'name' => 'Category1'
                    ]
                ],
                'expectedCategories' => [
                    'Category1'
                ],
                'expectedCatUrls' => [],
            ],
            'Integration type is API with nested categories' => [
                'integrationType' => 'API',
                'categories' => [
                    [
                        'id' => 'cce80a72bc3481d723c38cccf592d45a',
                        'name' => 'Category1',
                        'children' => [
                            [
                                'id' => 'f03d845e0abf31e72409cf7c5c704a2e',
                                'name' => 'Category2'
                            ]
                        ]
                    ]
                ],
                'expectedCategories' => [
                    'Category1_Category2'
                ],
                'expectedCatUrls' => [],
            ],
            'Integration type is DI and category is at first level' => [
                'integrationType' => 'Direct Integration',
                'categories' => [
                    [
                        'id' => 'cce80a72bc3481d723c38cccf592d45a',
                        'name' => 'Category1'
                    ]
                ],
                'expectedCategories' => [
                    'Category1'
                ],
                'expectedCatUrls' => [
                    '/Category1/',
                    '/navigation/cce80a72bc3481d723c38cccf592d45a'
                ],
            ],
            'Integration type is DI with nested categories' => [
                'integrationType' => 'Direct Integration',
                'categories' => [
                    [
                        'id' => 'cce80a72bc3481d723c38cccf592d45a',
                        'name' => 'Category1',
                        'children' => [
                            [
                                'id' => 'f03d845e0abf31e72409cf7c5c704a2e',
                                'name' => 'Category2'
                            ]
                        ]
                    ]
                ],
                'expectedCategories' => [
                    'Category1_Category2',
                ],
                'expectedCatUrls' => [
                    '/Category1/Category2/',
                    '/navigation/f03d845e0abf31e72409cf7c5c704a2e',
                    '/Category1/',
                    '/navigation/cce80a72bc3481d723c38cccf592d45a'
                ],
            ],
            'Integration type is unknown and category is at first level' => [
                'integrationType' => 'Unknown',
                'categories' => [
                    [
                        'id' => 'cce80a72bc3481d723c38cccf592d45a',
                        'name' => 'Category1'
                    ]
                ],
                'expectedCategories' => [
                    'Category1'
                ],
                'expectedCatUrls' => [],
            ],
            'Integration type is unknown with nested categories' => [
                'integrationType' => 'Unknown',
                'categories' => [
                    [
                        'id' => 'cce80a72bc3481d723c38cccf592d45a',
                        'name' => 'Category1',
                        'children' => [
                            [
                                'id' => 'f03d845e0abf31e72409cf7c5c704a2e',
                                'name' => 'Category2'
                            ]
                        ]
                    ]
                ],
                'expectedCategories' => [
                    'Category1_Category2'
                ],
                'expectedCatUrls' => [],
            ],
        ];
    }
}
