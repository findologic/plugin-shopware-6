<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\AssertionFailedError;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
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

    public function testFindsVariantForInactiveProduct(): void
    {
        // Main product is inactive.
        $inactiveProduct = $this->createVisibleTestProduct([
            'active' => false
        ]);

        $this->createVisibleTestProduct([
            'id' => Uuid::randomHex(),
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT',
            'stock' => 10,
            'active' => true,
            'parentId' => $inactiveProduct->getId(),
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
        ]);

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);
        $product = $products->first();

        $this->assertSame('FINDOLOGIC VARIANT', $product->getName());
    }

    public function testIfMoreThanOneVariantExistsItWillUseVariantInformationInsteadOfMainProductInformation(): void
    {
        $expectedParentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();

        $this->createVisibleTestProduct([
            'id' => $expectedParentId,
            'active' => false
        ]);

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
        ]));

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
        ]));

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);
        /** @var ProductEntity $product */
        $product = $products->first();

        // In the real world variants are created after another. When working with Shopware DAL manually,
        // sometimes the second statement may be executed before the first one, which causes a different result.
        // To prevent this test from failing if Shopware decides to create the second variant before the first one,
        // we ensure that the second variant is used instead.
        $expectedChildVariantId = $expectedSecondVariantId;
        try {
            $this->assertSame($expectedFirstVariantId, $product->getId());
        } catch (AssertionFailedError $e) {
            $this->assertSame($expectedSecondVariantId, $product->getId());
            $expectedChildVariantId = $expectedFirstVariantId;
        }

        $this->assertCount(2, $product->getChildren());

        foreach ($product->getChildren() as $child) {
            if (!$child->getParentId()) {
                $this->assertSame($expectedParentId, $child->getId());
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
                    'expressionForListings' => true,
                    'representation' => 'box'
                ]
            ]
        ]);

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'options' => [
                ['id' => $firstOptionId]
            ]
        ]));

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'options' => [
                ['id' => $secondOptionId]
            ]
        ]));

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

    public function mainVariantDefaultConfigProvider(): array
    {
        $parentId = Uuid::randomHex();

        return [
            'export shopware default' => [
                'config' => 'default',
                'parentId' => $parentId,
                'parentPrice' => 15,
                'cheapest' => 2
            ],
            'export main parent' => [
                'config' => 'parent',
                'parentId' => $parentId,
                'parentPrice' => 15,
                'cheapest' => 2
            ],
            'export cheapest variant' => [
                'config' => 'parent',
                'parentId' => $parentId,
                'parentPrice' => 15,
                'cheapest' => 2
            ],
            'export cheapest variant with parent price being cheaper' => [
                'config' => 'parent',
                'parentId' => $parentId,
                'parentPrice' => 3,
                'cheapest' => 5
            ],
            'export cheapest variant with all same prices' => [
                'config' => 'parent',
                'parentId' => $parentId,
                'parentPrice' => 4,
                'cheapest' => 4
            ],
        ];
    }

    /**
     * @dataProvider mainVariantDefaultConfigProvider
     */
    public function testProductWithMultipleVariantsBasedOnExportConfig(
        string $config,
        string $parentId,
        float $parentPrice,
        float $cheapestPrice
    ): void {
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();
        $expectedThirdVariantId = Uuid::randomHex();
        $expectedMainVariantId = $expectedSecondVariantId;

        $firstOptionId = Uuid::randomHex();
        $secondOptionId = Uuid::randomHex();
        $thirdOptionId = Uuid::randomHex();
        $optionGroupId = Uuid::randomHex();

        $this->createVisibleTestProduct([
            'id' => $parentId,
            'active' => false,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $parentPrice,
                    'net' => $parentPrice / 2,
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
            ],
            'configuratorGroupConfig' => [
                [
                    'id' => $optionGroupId,
                    // This tells Shopware to consider the mainVariationId if set.
                    'expressionForListings' => $config === 'parent',
                    'representation' => 'box'
                ]
            ],
        ]);

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $cheapestPrice,
                    'net' => $cheapestPrice / 2,
                    'linked' => false
                ]
            ],
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]));

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 4, 'net' => 2, 'linked' => false]],
            'options' => [
                ['id' => $secondOptionId]
            ],
        ]));

        $this->createVisibleTestProduct($this->getBasicVariantData([
            'id' => $expectedThirdVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.3',
            'name' => 'FINDOLOGIC VARIANT 3',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 4, 'net' => 2, 'linked' => false]],
            'options' => [
                ['id' => $thirdOptionId]
            ],
        ]));

        // Explicitly assign the main variant ID to the product, and assert that the assigned main variant
        // is the exported variant.
        if ($config === 'parent') {
            $expectedMainVariantId = $expectedThirdVariantId;
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
        }

        // Assert that the cheapest variant is exported as main variant.
        if ($config === 'cheapest') {
            $expectedMainVariantId = $expectedFirstVariantId;
            // If the parent price itself is the cheapest, we export that as the cheapest variant.
            if ($parentPrice <= $cheapestPrice) {
                $expectedMainVariantId = $parentId;
            }
        }

        $mockedConfig = $this->getFindologicConfig(['mainVariant' => $config]);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $this->defaultProductService->setConfig($mockedConfig);
        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertNotEmpty($elements);
        $this->assertCount(1, $elements);
        $mainVariant = current($elements);

        if ($config === 'default') {
            // Assert that exported main variant is one of the variants when using "default" configuration.
            $this->assertContains($expectedMainVariantId, [
                $expectedFirstVariantId,
                $expectedSecondVariantId,
                $expectedThirdVariantId
            ]);
        } else {
            $this->assertSame($expectedMainVariantId, $mainVariant->getId());
        }
    }

    /**
     * @dataProvider mainVariantDefaultConfigProvider
     */
    public function testProductWithoutVariantsBasedOnExportConfig(string $config, string $parentId): void
    {
        $this->createVisibleTestProduct(['id' => $parentId]);
        $mockedConfig = $this->getFindologicConfig(['mainVariant' => $config]);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $this->defaultProductService->setConfig($mockedConfig);
        $result = $this->defaultProductService->searchVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertNotEmpty($elements);
        $this->assertCount(1, $elements);
        $mainVariant = current($elements);

        // If there are no variants, the main product will always be exported as the main variant, irrespective
        // of the export configuration.
        $this->assertSame($parentId, $mainVariant->getId());
    }
}
