<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Search;

use FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder;
use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use FINDOLOGIC\FinSearch\Export\Search\VariantIterator;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ServicesHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Config\MainVariant;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use PHPUnit\Framework\AssertionFailedError;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity;

class ProductSearcherTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use ConfigHelper;
    use ServicesHelper;

    private SalesChannelContext $salesChannelContext;

    private ExportContext $exportContext;

    private ProductCriteriaBuilder $productCriteriaBuilder;

    private ProductSearcher $defaultProductSearcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->exportContext = $this->getExportContext(
            $this->salesChannelContext,
            $this->getCategory($this->salesChannelContext->getSalesChannel()->getNavigationCategoryId())
        );

        $this->productCriteriaBuilder = new ProductCriteriaBuilder($this->exportContext);
        $this->defaultProductSearcher = $this->getProductSearcher(
            $this->salesChannelContext,
            $this->getContainer(),
            $this->productCriteriaBuilder,
            $this->exportContext
        );
    }

    public function testFindsProductsAvailableForSearch(): void
    {
        $expectedProduct = $this->createVisibleTestProduct();

        $products = $this->defaultProductSearcher->findVisibleProducts(20, 0);
        $product = $products->first();

        $this->assertCount(1, $products);
        $this->assertSame($expectedProduct->id, $product->id);
    }

    public function testFindsProductId(): void
    {
        $expectedProduct = $this->createVisibleTestProduct();

        $products = $this->defaultProductSearcher->findVisibleProducts(20, 0, $expectedProduct->id);
        $product = $products->first();

        $this->assertCount(1, $products);
        $this->assertSame($expectedProduct->id, $product->id);
    }

    public function testIgnoresProductsWithPriceZero(): void
    {
        $this->createVisibleTestProduct(
            ['price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 0, 'net' => 0, 'linked' => false]]]
        );

        $products = $this->defaultProductSearcher->findVisibleProducts(20, 0);

        $this->assertCount(0, $products);
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
    public function testExportsAvailableVariantForProductsWithPriceZero(string $config): void
    {
        $variantInfo = array_merge(
            $this->getBasicVariantData([
                'id' => Uuid::randomHex(),
                'productNumber' => 'FINDOLOGIC001.1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
            ]),
            $this->getNameValues('FINDOLOGIC VARIANT')
        );

        $this->createVisibleTestProductWithCustomVariants([
            'active' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 0, 'net' => 0, 'linked' => false]]
        ], [$variantInfo]);

        $productSearcher = $this->getProductSearcher(
            $this->salesChannelContext,
            $this->getContainer(),
            $this->productCriteriaBuilder,
            $this->exportContext,
            ['mainVariant' => $config]
        );

        $products = $productSearcher->findVisibleProducts(20, 0);

        $this->assertCount(1, $products);
    }

    /**
     * @dataProvider mainVariantDefaultConfigProvider
     */
    public function testFindsVariantForInactiveProduct(string $config): void
    {
        $variantInfo = array_merge(
            $this->getBasicVariantData([
                'id' => Uuid::randomHex(),
                'productNumber' => 'FINDOLOGIC001.1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]]
            ]),
            $this->getNameValues('FINDOLOGIC VARIANT')
        );

        $this->createVisibleTestProductWithCustomVariants([
            'active' => false,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
        ], [$variantInfo]);

        $productSearcher = $this->getProductSearcher(
            $this->salesChannelContext,
            $this->getContainer(),
            $this->productCriteriaBuilder,
            $this->exportContext,
            ['mainVariant' => $config]
        );

        $products = $productSearcher->findVisibleProducts(20, 0);
        $product = $products->first();

        $this->assertCount(1, $products);
        // They started to return the correct translation, instead of the defined product name
        if (Utils::versionGreaterOrEqual('6.4.11.0')) {
            $this->assertSame('FINDOLOGIC VARIANT EN', $product->name);
        } else {
            $this->assertSame('FINDOLOGIC VARIANT', $product->name);
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

    private function getChildrenVariants(VariantIterator $childProductIterator): array
    {
        $childVariants = [];

        while (($variants = $childProductIterator->fetch()) !== null) {
            foreach ($variants as $variant) {
                $childVariants[] = $variant;
            }
        }

        return $childVariants;
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

        $products = $this->defaultProductSearcher->findVisibleProducts(20, 0);
        /** @var ProductEntity $product */
        $product = $products->first();

        // In the real world variants are created after another. When working with Shopware DAL manually,
        // sometimes the second statement may be executed before the first one, which causes a different result.
        // To prevent this test from failing if Shopware decides to create the second variant before the first one,
        // we ensure that the second variant is used instead.
        $expectedFirstVariantId = $variantIds[0];
        $expectedSecondVariantId = $variantIds[1];
        $expectedChildVariantId = $expectedSecondVariantId;

        $childProductIterator = $this->defaultProductSearcher->buildVariantIterator($product, 5);
        /** @var ProductEntity[] $childVariants */
        $childVariants = $this->getChildrenVariants($childProductIterator);

        try {
            $this->assertSame($expectedFirstVariantId, $product->id);
        } catch (AssertionFailedError $e) {
            $this->assertSame($expectedSecondVariantId, $product->id);
            $expectedChildVariantId = $expectedFirstVariantId;
        }

        $this->assertCount($expectedChildCount, $childVariants);

        foreach ($childVariants as $child) {
            if (!$child->parentId) {
                $this->assertSame($mainProduct['id'], $child->id);
            } else {
                $this->assertSame($expectedChildVariantId, $child->id);
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

        $result = $this->defaultProductSearcher->findVisibleProducts(20, 0);
        $this->assertCount(2, $result->getElements());

        $products = array_values($result->getElements());
        $this->assertSame($expectedFirstVariantId, $products[0]->id);
        $this->assertSame($expectedSecondVariantId, $products[1]->id);
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

        $variants = [];
        $variants[] = $this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'options' => [
                ['id' => $secondOptionId]
            ],
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
                    // Explicitly set this to false. This tells Shopware to consider the mainVariationId (if set).
                    'expressionForListings' => false,
                    'representation' => 'box'
                ]
            ],
        ], $variants);

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

        $result = $this->defaultProductSearcher->findVisibleProducts(20, 0);
        $this->assertCount(1, $result->getElements());

        $product = $result->first();
        $this->assertSame($expectedMainVariantId, $product->id);

        $childrenIterator = $this->defaultProductSearcher->buildVariantIterator($product, 20);
        /** @var ProductEntity[] $childrenVariants */
        $childrenVariants = $this->getChildrenVariants($childrenIterator);

        $this->assertCount(2, $childrenVariants);
        foreach ($childrenVariants as $child) {
            if ($child->parentId === null) {
                $this->assertSame($expectedParentId, $child->id);
            } else {
                $this->assertSame($expectedFirstVariantId, $child->id);
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

        $variants = [];
        $variants[] = $this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $expectedParentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'active' => false,
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
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
                    // Explicitly set this to false. This tells Shopware to consider the mainVariationId (if set).
                    'expressionForListings' => false,
                    'representation' => 'box'
                ]
            ],
        ], $variants);

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

        $result = $this->defaultProductSearcher->findVisibleProducts(20, 0);

        $this->assertEmpty($result->getElements());
    }

    public function testProductWithMultipleVariantsIfExportConfigIsParent(): void
    {
        $parentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();
        $expectedThirdVariantId = Uuid::randomHex();
        $expectedMainVariantId = $expectedFirstVariantId;

        $this->createProductWithMultipleVariants(
            $parentId,
            $expectedFirstVariantId,
            $expectedSecondVariantId,
            $expectedThirdVariantId
        );

        $productSearcher = $this->getProductSearcher(
            $this->salesChannelContext,
            $this->getContainer(),
            $this->productCriteriaBuilder,
            $this->exportContext,
            ['mainVariant' => MainVariant::MAIN_PARENT]
        );
        $result = $productSearcher->findVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertCount(1, $elements);
        $mainVariant = current($elements);

        $this->assertSame($expectedMainVariantId, $mainVariant->id);
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

        $result = $this->defaultProductSearcher->findVisibleProducts(20, 0);
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
            'export cheapest real price with one having 0' => [
                'parentPrice' => 12,
                'firstVariantPrice' => 4,
                'secondVariantPrice' => 0,
                'thirdVariantPrice' => 9,
                'cheapestPrice' => 4
            ],
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

        $productSearcher = $this->getProductSearcher(
            $this->salesChannelContext,
            $this->getContainer(),
            $this->productCriteriaBuilder,
            $this->exportContext,
            ['mainVariant' => MainVariant::CHEAPEST]
        );
        $result = $productSearcher->findVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertCount(1, $elements);
        $mainVariant = current($elements);

        $this->assertSame($expectedMainVariantId, $mainVariant->id);
    }

    /**
     * @dataProvider mainVariantDefaultConfigProvider
     */
    public function testProductWithoutVariantsBasedOnExportConfig(string $config): void
    {
        $parentId = Uuid::randomHex();
        $this->createVisibleTestProduct(['id' => $parentId]);
        $productSearcher = $this->getProductSearcher(
            $this->salesChannelContext,
            $this->getContainer(),
            $this->productCriteriaBuilder,
            $this->exportContext,
            ['mainVariant' => $config]
        );
        $result = $productSearcher->findVisibleProducts(20, 0);
        $elements = $result->getElements();

        $this->assertCount(1, $elements);
        $mainVariant = current($elements);

        // If there are no variants, the main product will always be exported as the main variant, irrespective
        // of the export configuration.
        $this->assertSame($parentId, $mainVariant->id);
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

        $productSearcher = $this->getProductSearcher(
            $this->salesChannelContext,
            $this->getContainer(),
            $this->productCriteriaBuilder,
            $this->exportContext,
            ['mainVariant' => MainVariant::CHEAPEST]
        );
        $result = $productSearcher->findVisibleProducts(20, 0);
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

        $products = $this->defaultProductSearcher->findVisibleProducts(20, 0);
        $productsKeyed = $products->getElements();

        $this->assertEquals($beforeId, $productsKeyed[0]->id);
        $this->assertEquals($productId5, $productsKeyed[1]->id);
        $this->assertEquals($productId4, $productsKeyed[2]->id);
        $this->assertEquals($productId2, $productsKeyed[3]->id);
        $this->assertEquals($productId3, $productsKeyed[4]->id);
        $this->assertEquals($productId1, $productsKeyed[5]->id);
        $this->assertEquals($afterId, $productsKeyed[6]->id);
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
