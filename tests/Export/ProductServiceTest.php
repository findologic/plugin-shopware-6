<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\AssertionFailedError;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use ConfigHelper;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ProductService */
    private $defaultProductService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->defaultProductService = ProductService::getInstance(
            $this->getContainer(),
            $this->salesChannelContext
        );
    }

    public function testFindsAllProducts(): void
    {
        $expectedProduct = $this->createTestProduct();

        $products = $this->defaultProductService->searchAllProducts(20, 0);

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($expectedProduct->getId(), $product->getId());
    }

    public function testFindsProductsAvailableForSearch(): void
    {
        $expectedProduct = $this->createVisibleTestProduct();

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($expectedProduct->getId(), $product->getId());
    }

    public function testFindsProductId(): void
    {
        $expectedProduct = $this->createVisibleTestProduct();

        $products = $this->defaultProductService->searchVisibleProducts(20, 0, $expectedProduct->getId());

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($expectedProduct->getId(), $product->getId());
    }

    public function testGetInstancePopulatesSalesChannelContext(): void
    {
        $productService = new ProductService($this->getContainer());
        $this->getContainer()->set(ProductService::CONTAINER_ID, $productService);

        $this->assertNull($productService->getSalesChannelContext());

        $actualProductService = ProductService::getInstance($this->getContainer(), $this->salesChannelContext);
        $this->assertSame($productService, $actualProductService);
        $this->assertInstanceOf(SalesChannelContext::class, $productService->getSalesChannelContext());
        $this->assertSame($this->salesChannelContext, $productService->getSalesChannelContext());
    }

    public function testIgnoresProductsWithPriceZero(): void
    {
        $this->createVisibleTestProduct(
            ['price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 0, 'net' => 0, 'linked' => false]]]
        );

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);

        $this->assertCount(0, $products);
    }

    public function testFindsVariantForInactiveProduct(): void
    {
        $variantInfo = array_merge([
            'id' => Uuid::randomHex(),
            'productNumber' => 'FINDOLOGIC001.1',
            'stock' => 10,
            'active' => true,
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
        ], $this->getNameValues('FINDOLOGIC VARIANT'));

        // Main product is inactive.
        $this->createVisibleTestProductWithCustomVariants([
            'active' => false
        ], [$variantInfo]);

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);
        $product = $products->first();

        if (Utils::versionGreaterOrEqual('v6.4.11.0')) {
            $this->assertSame('FINDOLOGIC VARIANT EN', $product->getName());
        } else {
            $this->assertSame('FINDOLOGIC VARIANT', $product->getName());
        }
    }

    public function variantProvider(): array
    {
        $expectedParentId = Uuid::randomHex();

        return [
            'variant information is used instead of product information' => [
                'mainProduct' => [
                    'id' => $expectedParentId,
                    'active' => false,
                ],
                'variants' => [
                    [
                        'id' => Uuid::randomHex(),
                        'parentId' => $expectedParentId,
                        'active' => true,
                        'productNumber' => 'FINDOLOGIC001.1',
                        'name' => 'FINDOLOGIC VARIANT 1',
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'parentId' => $expectedParentId,
                        'active' => true,
                        'productNumber' => 'FINDOLOGIC001.2',
                        'name' => 'FINDOLOGIC VARIANT 2',
                    ],
                ],
                'expectedChildCount' => 2,
            ],
            'inactive variants are not considered' => [
                'mainProduct' => [
                    'id' => $expectedParentId,
                    'active' => true,
                ],
                'variants' => [
                    [
                        'id' => Uuid::randomHex(),
                        'parentId' => $expectedParentId,
                        'active' => false,
                        'productNumber' => 'FINDOLOGIC001.1',
                        'name' => 'FINDOLOGIC VARIANT 1',
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'parentId' => $expectedParentId,
                        'active' => true,
                        'productNumber' => 'FINDOLOGIC001.2',
                        'name' => 'FINDOLOGIC VARIANT 2',
                    ],
                ],
                'expectedChildCount' => 1,
            ],
        ];
    }

    /**
     * @dataProvider variantProvider
     */
    public function testVariantDataGetsExportedDependingOnActiveState(
        array $mainProduct,
        array $variants,
        int $expectedChildCount
    ): void {
        $variantIds = [];
        $customVariants = [];
        foreach ($variants as $variant) {
            $variantIds[] = $variant['id'];
            $customVariants[] = $this->getBasicVariantData($variant);
        }

        $this->createVisibleTestProductWithCustomVariants($mainProduct, $customVariants);

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);
        /** @var ProductEntity $product */
        $product = $products->first();

        // In the real world variants are created after another. When working with Shopware DAL manually,
        // sometimes the second statement may be executed before the first one, which causes a different result.
        // To prevent this test from failing if Shopware decides to create the second variant before the first one,
        // we ensure that the second variant is used instead.
        $expectedFirstVariantId = $variantIds[0];
        $expectedSecondVariantId = $variantIds[1];
        $expectedChildVariantId = $expectedSecondVariantId;
        try {
            $this->assertSame($expectedFirstVariantId, $product->getId());
        } catch (AssertionFailedError $e) {
            $this->assertSame($expectedSecondVariantId, $product->getId());
            $expectedChildVariantId = $expectedFirstVariantId;
        }

        $this->assertCount($expectedChildCount, $product->getChildren());

        foreach ($product->getChildren() as $child) {
            if (!$child->getParentId()) {
                $this->assertSame($mainProduct['id'], $child->getId());
            } else {
                $this->assertSame($expectedChildVariantId, $child->getId());
            }
        }
    }

    public function testVariantsWithSameParentButDifferentDisplayGroupAreExportedAsSeparateProducts(): void
    {
        $expectedParentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();

        $firstOptionId = Uuid::randomHex();
        $secondOptionId = Uuid::randomHex();
        $optionGroupId = Uuid::randomHex();

        $variants = [];
        $variants[] = $this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'options' => [
                ['id' => $firstOptionId]
            ]
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'options' => [
                ['id' => $secondOptionId]
            ]
        ]);

        $this->createVisibleTestProductWithCustomVariants([
            'id' => $expectedParentId,
            'active' => false,
            'configuratorSettings' => [
                [
                    'option' => [
                        'id' => $firstOptionId,
                        'name' => 'Red',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $secondOptionId,
                        'name' => 'Orange',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
            ],
            'configuratorGroupConfig' => [
                [
                    'id' => $optionGroupId,
                    'expressionForListings' => true,
                    'representation' => 'box'
                ]
            ]
        ], $variants);

        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $this->assertCount(2, $result->getElements());

        $products = array_values($result->getElements());
        $this->assertSame($expectedFirstVariantId, $products[0]->getId());
        $this->assertSame($expectedSecondVariantId, $products[1]->getId());
    }

    public function testTheMainProductIsBasedOnTheMainVariantIdOfADisplayGroup(): void
    {
        $expectedParentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();
        $expectedMainVariantId = $expectedSecondVariantId;

        $firstOptionId = Uuid::randomHex();
        $secondOptionId = Uuid::randomHex();
        $optionGroupId = Uuid::randomHex();

        $this->createVisibleTestProduct([
            'id' => $expectedParentId,
            'active' => false,
            'configuratorSettings' => [
                [
                    'option' => [
                        'id' => $firstOptionId,
                        'name' => 'Red',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $secondOptionId,
                        'name' => 'Orange',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
            ],
            'configuratorGroupConfig' => [
                [
                    'id' => $optionGroupId,
                    // Explicitly set this to false. This tells Shopware to consider the mainVariationId (if set).
                    'expressionForListings' => false,
                    'representation' => 'box'
                ]
            ],
        ]);

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]));

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'options' => [
                ['id' => $secondOptionId]
            ],
        ]));

        $this->getContainer()->get('product.repository')->update([
            [
                'id' => $expectedFirstVariantId,
                'mainVariantId' => $expectedMainVariantId
            ],
            [
                'id' => $expectedSecondVariantId,
                'mainVariantId' => $expectedMainVariantId
            ]
        ], Context::createDefaultContext());

        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $this->assertCount(1, $result->getElements());

        $product = $result->first();
        $this->assertSame($expectedMainVariantId, $product->getId());

        $this->assertCount(2, $product->getChildren());
        foreach ($product->getChildren() as $child) {
            if ($child->getParentId() === null) {
                $this->assertSame($expectedParentId, $child->getId());
            } else {
                $this->assertSame($expectedFirstVariantId, $child->getId());
            }
        }
    }

    public function testProductIsNotFoundIfMainVariantIsNotAvailableForSalesChannel(): void
    {
        $expectedParentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();
        $expectedMainVariantId = $expectedSecondVariantId;

        $firstOptionId = Uuid::randomHex();
        $secondOptionId = Uuid::randomHex();
        $optionGroupId = Uuid::randomHex();

        $this->createVisibleTestProduct([
            'id' => $expectedParentId,
            'active' => false,
            'configuratorSettings' => [
                [
                    'option' => [
                        'id' => $firstOptionId,
                        'name' => 'Red',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $secondOptionId,
                        'name' => 'Orange',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
            ],
            'configuratorGroupConfig' => [
                [
                    'id' => $optionGroupId,
                    // Explicitly set this to false. This tells Shopware to consider the mainVariationId (if set).
                    'expressionForListings' => false,
                    'representation' => 'box'
                ]
            ],
        ]);

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'active' => false,
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]));

        // Create main variant, but make it not available for the sales channel.
        $this->createTestProduct($this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'options' => [
                ['id' => $secondOptionId]
            ],
            'visibilities' => [
                [
                    'id' => Uuid::randomHex(),
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => 0
                ]
            ]
        ]));

        $this->getContainer()->get('product.repository')->update([
            [
                'id' => $expectedFirstVariantId,
                'mainVariantId' => $expectedMainVariantId
            ],
            [
                'id' => $expectedSecondVariantId,
                'mainVariantId' => $expectedMainVariantId
            ]
        ], Context::createDefaultContext());

        $result = $this->defaultProductService->searchVisibleProducts(20, 0);

        $this->assertEmpty($result->getElements());
    }

    public function testProductWithMultipleVariantsIfExportConfigIsParent(): void
    {
        $parentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();
        $expectedThirdVariantId = Uuid::randomHex();
        $expectedMainVariantId = $parentId;

        $this->createProductWithMultipleVariants(
            $parentId,
            $expectedFirstVariantId,
            $expectedSecondVariantId,
            $expectedThirdVariantId
        );

        $mockedConfig = $this->getFindologicConfig(['mainVariant' => 'parent']);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $this->defaultProductService->setConfig($mockedConfig);
        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertCount(1, $elements);
        $mainVariant = current($elements);

        $this->assertSame($expectedMainVariantId, $mainVariant->getId());
    }

    public function testProductWithMultipleVariantsIfExportConfigIsDefault(): void
    {
        $parentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();
        $expectedThirdVariantId = Uuid::randomHex();
        $expectedMainVariantId = $expectedSecondVariantId;

        $this->createProductWithMultipleVariants(
            $parentId,
            $expectedFirstVariantId,
            $expectedSecondVariantId,
            $expectedThirdVariantId
        );

        $mockedConfig = $this->getFindologicConfig(['mainVariant' => 'default']);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $this->defaultProductService->setConfig($mockedConfig);
        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertCount(1, $elements);

        // Assert that exported main variant is one of the variants when using "default" configuration.
        $this->assertContains($expectedMainVariantId, [
            $expectedFirstVariantId,
            $expectedSecondVariantId,
            $expectedThirdVariantId
        ]);
    }

    public function mainVariantCheapestProvider(): array
    {
        return [
            'export cheapest variant' => [
                'parentPrice' => 15,
                'firstVariantPrice' => 2,
                'secondVariantPrice' => 6,
                'thirdVariantPrice' => 4,
                'cheapestPrice' => 2
            ],
            'export cheapest variant with parent price being cheaper' => [
                'parentPrice' => 3,
                'firstVariantPrice' => 10,
                'secondVariantPrice' => 10,
                'thirdVariantPrice' => 10,
                'cheapest' => 3
            ],
            'export cheapest variant with all same prices' => [
                'parentPrice' => 4,
                'firstVariantPrice' => 4,
                'secondVariantPrice' => 4,
                'thirdVariantPrice' => 4,
                'cheapestPrice' => 4
            ],
            'export cheapest real price with one having 0' => [
                'parentPrice' => 12,
                'firstVariantPrice' => 4,
                'secondVariantPrice' => 0,
                'thirdVariantPrice' => 9,
                'cheapestPrice' => 4
            ],
            'export cheapest variant price with parent having 0' => [
                'parentPrice' => 0,
                'firstVariantPrice' => 4,
                'secondVariantPrice' => 5,
                'thirdVariantPrice' => 9,
                'cheapestPrice' => 4
            ]
        ];
    }

    /**
     * @dataProvider mainVariantCheapestProvider
     */
    public function testProductWithMultipleVariantsIfExportConfigIsCheapest(
        float $parentPrice,
        float $firstVariantPrice,
        float $secondVariantPrice,
        float $thirdVariantPrice,
        float $cheapestPrice
    ): void {
        $parentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();
        $expectedThirdVariantId = Uuid::randomHex();

        $this->createProductWithDifferentPriceVariants(
            $parentId,
            $parentPrice,
            $expectedFirstVariantId,
            $firstVariantPrice,
            $expectedSecondVariantId,
            $secondVariantPrice,
            $expectedThirdVariantId,
            $thirdVariantPrice
        );

        // By default, main product will be exported, unless there is a cheaper price.
        $expectedMainVariantId = $parentId;
        if (
            $parentPrice === 0.0 ||
            $cheapestPrice < $parentPrice
        ) {
            $expectedMainVariantId = $expectedFirstVariantId;
        }

        $mockedConfig = $this->getFindologicConfig(['mainVariant' => 'cheapest']);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $this->defaultProductService->setConfig($mockedConfig);
        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertCount(1, $elements);
        $mainVariant = current($elements);

        $this->assertSame($expectedMainVariantId, $mainVariant->getId());
    }

    public function mainVariantDefaultConfigProvider(): array
    {
        return [
            'export shopware default' => ['config' => 'default'],
            'export main parent' => ['config' => 'parent'],
            'export cheapest variant' => ['config' => 'cheapest']
        ];
    }

    /**
     * @dataProvider mainVariantDefaultConfigProvider
     */
    public function testProductWithoutVariantsBasedOnExportConfig(string $config): void
    {
        $parentId = Uuid::randomHex();
        $this->createVisibleTestProduct(['id' => $parentId]);
        $mockedConfig = $this->getFindologicConfig(['mainVariant' => $config]);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $this->defaultProductService->setConfig($mockedConfig);
        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertCount(1, $elements);
        $mainVariant = current($elements);

        // If there are no variants, the main product will always be exported as the main variant, irrespective
        // of the export configuration.
        $this->assertSame($parentId, $mainVariant->getId());
    }

    public function testProductIsNotSkippedWhenExportedMainVariantIsNotAvailable(): void
    {
        if (Utils::versionLowerThan('6.4.4')) {
            $this->markTestSkipped('Main variant id logic only exists since newer Shopware versions');
        }

        $parentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();
        $expectedThirdVariantId = Uuid::randomHex();

        $this->createProductWithDifferentPriceVariants(
            $parentId,
            100,
            $expectedFirstVariantId,
            20,
            $expectedSecondVariantId,
            40,
            $expectedThirdVariantId,
            60
        );

        $this->getContainer()->get('product.repository')->update([
            [
                'id' => $parentId,
                'active' => false
            ],
            [
                'id' => $expectedFirstVariantId,
                'mainVariantId' => $parentId,
            ],
            [
                'id' => $expectedSecondVariantId,
                'mainVariantId' => $parentId
            ],
            [
                'id' => $expectedThirdVariantId,
                'mainVariantId' => $parentId
            ]
        ], Context::createDefaultContext());

        $mockedConfig = $this->getFindologicConfig(['mainVariant' => 'cheapest']);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $this->defaultProductService->setConfig($mockedConfig);
        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertCount(1, $elements);
    }

    public function testProductsAreSortedByCreateDateAndId(): void
    {
        $beforeId = '503c73e48f4d4d8092265296191d5c5a';
        $productId1 = 'ee37428996b7495880ea677d110961f6';
        $productId2 = '6ac33a527a454b9ead9384e84f17d98f';
        $productId3 = '8e86114cafeb43fab70d77b7c1a7baf7';
        $productId4 = '3b10a796c9044658ac41ff564dec62d3';
        $productId5 = '2f2aa85d87cc4d0390a5213c1bdffae5';
        $sameDateIds = [$productId1, $productId2, $productId3, $productId4, $productId5];
        $afterId = 'e6a13c9d4a06472ab5bd7b6053e6e422';

        $before = '2022-04-07 08:34:05.605';
        $now = '2022-04-07 09:34:05.605';
        $after = '2022-04-07 10:34:05.605';

        $this->createVisibleTestProduct(['id' => $beforeId, 'productNumber' => $beforeId]);
        $this->createVisibleTestProduct(['id' => $afterId, 'productNumber' => $afterId]);
        $this->setCreatedAtValue($beforeId, $before);
        $this->setCreatedAtValue($afterId, $after);

        foreach ($sameDateIds as $sameDateId) {
            $this->createVisibleTestProduct(['id' => $sameDateId, 'productNumber' => $sameDateId]);
            $this->setCreatedAtValue($sameDateId, $now);
        }

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);
        $productsKeyed = array_keys($products->getElements());

        $this->assertEquals($beforeId, $productsKeyed[0]);
        $this->assertEquals($productId5, $productsKeyed[1]);
        $this->assertEquals($productId4, $productsKeyed[2]);
        $this->assertEquals($productId2, $productsKeyed[3]);
        $this->assertEquals($productId3, $productsKeyed[4]);
        $this->assertEquals($productId1, $productsKeyed[5]);
        $this->assertEquals($afterId, $productsKeyed[6]);
    }

    private function setCreatedAtValue($productNumber, $created_at): void
    {
        $connection = $this->getKernel()->getConnection();

        $connection->update('product', ['created_at' => $created_at], ['product_number' => $productNumber]);
    }

    private function createProductWithMultipleVariants(
        string $parentId,
        string $expectedFirstVariantId,
        string $expectedSecondVariantId,
        string $expectedThirdVariantId
    ): void {
        $firstOptionId = Uuid::randomHex();
        $secondOptionId = Uuid::randomHex();
        $thirdOptionId = Uuid::randomHex();
        $optionGroupId = Uuid::randomHex();

        $variants = [];
        $variants[] = $this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'options' => [
                ['id' => $secondOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedThirdVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.3',
            'name' => 'FINDOLOGIC VARIANT 3',
            'options' => [
                ['id' => $thirdOptionId]
            ],
        ]);

        $this->createVisibleTestProductWithCustomVariants([
            'id' => $parentId,
            'active' => false,
            'configuratorSettings' => [
                [
                    'option' => [
                        'id' => $firstOptionId,
                        'name' => 'Red',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $secondOptionId,
                        'name' => 'Orange',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $thirdOptionId,
                        'name' => 'Green',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
            ]
        ], $variants);
    }

    private function createProductWithDifferentPriceVariants(
        string $parentId,
        float $parentPrice,
        string $expectedFirstVariantId,
        float $firstVariantPrice,
        string $expectedSecondVariantId,
        float $secondVariantPrice,
        string $expectedThirdVariantId,
        float $thirdVariantPrice
    ): void {
        $firstOptionId = Uuid::randomHex();
        $secondOptionId = Uuid::randomHex();
        $thirdOptionId = Uuid::randomHex();
        $optionGroupId = Uuid::randomHex();

        $variants = [];
        $variants[] = $this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $firstVariantPrice,
                    'net' => $firstVariantPrice,
                    'linked' => false
                ]
            ],
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $secondVariantPrice,
                    'net' => $secondVariantPrice,
                    'linked' => false
                ]
            ],
            'options' => [
                ['id' => $secondOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedThirdVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.3',
            'name' => 'FINDOLOGIC VARIANT 3',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $thirdVariantPrice,
                    'net' => $thirdVariantPrice,
                    'linked' => false
                ]
            ],
            'options' => [
                ['id' => $thirdOptionId]
            ],
        ]);

        $this->createVisibleTestProductWithCustomVariants([
            'id' => $parentId,
            'active' => false,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $parentPrice,
                    'net' => $parentPrice,
                    'linked' => false
                ]
            ],
            'configuratorSettings' => [
                [
                    'option' => [
                        'id' => $firstOptionId,
                        'name' => 'Red',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $secondOptionId,
                        'name' => 'Orange',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $thirdOptionId,
                        'name' => 'Green',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
            ]
        ], $variants);
    }
}
