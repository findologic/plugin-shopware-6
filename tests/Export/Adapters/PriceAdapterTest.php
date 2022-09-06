<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\Adapters\PriceAdapter;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Export\Providers\CustomerGroupContextProvider;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\AdvancedPriceHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PriceAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use ConfigHelper;
    use PluginConfigHelper;
    use AdvancedPriceHelper;

    protected SalesChannelContext $salesChannelContext;

    protected ExportContext $exportContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->exportContext = new ExportContext(
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            $this->salesChannelContext->getSalesChannel()->getNavigationCategory()
        );

        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->getContainer()->set('fin_search.export_context', $this->exportContext);
    }

    public function testExceptionIsThrownIfProductHasNoPrices(): void
    {
        $this->expectException(ProductHasNoPricesException::class);

        $adapter = $this->getContainer()->get(PriceAdapter::class);

        // Use mock as a product without a price can only be manually added to the database. Shopware DAL would throw
        // an error.
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())->method('getPrice')->willReturn(new PriceCollection());

        $adapter->adapt($product);
    }

    public function testPriceContainsConfiguredProductPrice(): void
    {
        $expectedPrice = 13.37;

        $adapter = $this->getContainer()->get(PriceAdapter::class);
        $product = $this->createTestProduct([
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $expectedPrice,
                    'net' => 10,
                    'linked' => false
                ]
            ]
        ]);

        $prices = $adapter->adapt($product);

        $this->assertCount(1, $prices);
        $this->assertCount(1, $prices[0]->getValues());
        $this->assertEquals($expectedPrice, $prices[0]->getValues()['']);
    }

    public function customerGroupsProvider(): array
    {
        $grossCustomerGroup = new CustomerGroupEntity();
        $grossCustomerGroup->setId(Uuid::randomHex());
        $grossCustomerGroup->setDisplayGross(true);

        $netCustomerGroup = new CustomerGroupEntity();
        $netCustomerGroup->setId(Uuid::randomHex());
        $netCustomerGroup->setDisplayGross(false);

        return [
            'Gross customer group' => [
                'grossPrice' => 13.37,
                'netPrice' => 10.11,
                'customerGroups' => [
                    $grossCustomerGroup
                ],
                'expectedPrices' => [
                    $grossCustomerGroup->getId() => 13.37
                ]
            ],
            'Net customer group' => [
                'grossPrice' => 13.37,
                'netPrice' => 10.11,
                'customerGroups' => [
                    $netCustomerGroup
                ],
                'expectedPrices' => [
                    $netCustomerGroup->getId() => 10.11
                ]
            ],
            'Net and gross customer group' => [
                'grossPrice' => 13.37,
                'netPrice' => 10.11,
                'customerGroups' => [
                    $grossCustomerGroup,
                    $netCustomerGroup
                ],
                'expectedPrices' => [
                    $grossCustomerGroup->getId() => 13.37,
                    $netCustomerGroup->getId() => 10.11
                ]
            ]
        ];
    }

    /**
     * @runInSeparateProcess
     * @dataProvider customerGroupsProvider
     * @param CustomerGroupEntity[] $customerGroups
     * @param array<string, float> $expectedPrices
     * @throws ProductHasNoPricesException
     */
    public function testPriceIsExportedForCustomerGroups(
        float $grossPrice,
        float $netPrice,
        array $customerGroups,
        array $expectedPrices
    ): void {
        /** @var ExportContext $exportContext */
        $exportContext = $this->getContainer()->get('fin_search.export_context');
        $exportContext->setCustomerGroups($customerGroups);

        $adapter = $this->getContainer()->get(PriceAdapter::class);
        $product = $this->createTestProduct([
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $grossPrice,
                    'net' => $netPrice,
                    'linked' => false
                ]
            ]
        ]);

        $prices = $adapter->adapt($product);

        $expectedGroupPrices = count($expectedPrices);
        $actualGroupPrices = 0;
        foreach ($customerGroups as $customerGroup) {
            $userGroup = Utils::calculateUserGroupHash($exportContext->getShopkey(), $customerGroup->getId());

            foreach ($prices as $price) {
                foreach ($price->getValues() as $group => $value) {
                    if ($userGroup === $group) {
                        $this->assertEquals($expectedPrices[$customerGroup->getId()], $value);
                        $actualGroupPrices++;
                    }
                }
            }
        }

        $this->assertEquals($expectedGroupPrices, $actualGroupPrices, sprintf(
            'Expected %d group(s) to have prices. Actual price count: %d',
            $expectedGroupPrices,
            $actualGroupPrices
        ));
    }

    public function testProductPriceWithCurrency(): void
    {
        $currencyId = $this->createCurrency();
        $this->salesChannelContext->getSalesChannel()->setCurrencyId($currencyId);

        $adapter = new PriceAdapter(
            $this->salesChannelContext,
            $this->exportContext,
            $this->getContainer()->get(ProductPriceCalculator::class),
            $this->getContainer()->get(CustomerGroupContextProvider::class),
            $this->getContainer()->get(Config::class),
            '6.4.9.1'
        );
        $testProduct = $this->createTestProduct([
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ['currencyId' => $currencyId, 'gross' => 7.5, 'net' => 5, 'linked' => false]
            ]
        ]);

        $prices = $adapter->adapt($testProduct);
        $priceValues = current($prices)->getValues();

        $this->assertEquals(1, count($prices));
        $this->assertEquals(7.5, current($priceValues));
    }

    public function advancedPricesTestProvider(): array
    {
        $grossCustomerGroupId = Uuid::randomHex();
        $netCustomerGroupId = Uuid::randomHex();

        $groupsData = $this->getAdvancedPricesTestGroupData(
            [
                [
                    'groupId' => $netCustomerGroupId,
                    'displayGross' => false,
                    'prices' => [
                        ['qtyMin' => 1, 'qtyMax' => 10, 'gross' => 6, 'net' => 5],
                        ['qtyMin' => 11, 'qtyMax' => 20, 'gross' => 2,'net' => 1]
                    ]
                ],
                [
                    'groupId' => $grossCustomerGroupId,
                    'displayGross' => true,
                    'prices' => [
                        ['qtyMin' => 1, 'qtyMax' => 10, 'gross' => 4, 'net' => 3],
                        ['qtyMin' => 11, 'qtyMax' => 20, 'gross' => 8, 'net' => 2],
                    ]
                ]
            ]
        );

        return [
            'Test cheapest advanced price configuration' => [
                'groupsData' => $groupsData,
                'advancedPricingConfig' => 'cheapest',
                'expectedPrices' => [
                    $netCustomerGroupId => 1,
                    $grossCustomerGroupId => 4
                ]
            ],
            'Test unit advanced price configuration' => [
                'groupsData' => $groupsData,
                'advancedPricingConfig' => 'unit',
                'expectedPrices' => [
                    $netCustomerGroupId => 5,
                    $grossCustomerGroupId => 4
                ]
            ],
            'Test off advanced price configuration' => [
                'groupsData' => $groupsData,
                'advancedPricingConfig' => 'off',
                'expectedPrices' => [
                    $netCustomerGroupId => 10,
                    $grossCustomerGroupId => 15
                ]
            ],
            'Test unit config and no prices for gross customer' => [
                'groupsData' => $this->getAdvancedPricesTestGroupData(
                    [
                        [
                            'groupId' => $netCustomerGroupId,
                            'displayGross' => false,
                            'prices' => [
                                ['qtyMin' => 1, 'qtyMax' => 10, 'gross' => 6, 'net' => 5],
                                ['qtyMin' => 11, 'qtyMax' => 20, 'gross' => 2,'net' => 1]
                            ]
                        ],
                        [
                            'groupId' => $grossCustomerGroupId,
                            'displayGross' => true,
                            'prices' => []
                        ]
                    ]
                ),
                'advancedPricingConfig' => 'unit',
                'expectedPrices' => [
                    $netCustomerGroupId => 5,
                    $grossCustomerGroupId => 15
                ]
            ],
            'Test unit config and no advanced prices for net customer' => [
                'groupsData' => $this->getAdvancedPricesTestGroupData(
                    [
                        [
                            'groupId' => $netCustomerGroupId,
                            'displayGross' => false,
                            'prices' => []
                        ],
                        [
                            'groupId' => $grossCustomerGroupId,
                            'displayGross' => true,
                            'prices' => [
                                ['qtyMin' => 1, 'qtyMax' => 10, 'gross' => 4, 'net' => 3],
                                ['qtyMin' => 11, 'qtyMax' => 20, 'gross' => 8, 'net' => 2],
                            ]
                        ]
                    ]
                ),
                'advancedPricingConfig' => 'unit',
                'expectedPrices' => [
                    $netCustomerGroupId => 10,
                    $grossCustomerGroupId => 4
                ]
            ],
            'Test unit config and no advanced prices for gross customer' => [
                'groupsData' => $this->getAdvancedPricesTestGroupData(
                    [
                        [
                            'groupId' => $netCustomerGroupId,
                            'displayGross' => false,
                            'prices' => [
                                ['qtyMin' => 1, 'qtyMax' => 10, 'gross' => 4, 'net' => 3],
                                ['qtyMin' => 11, 'qtyMax' => 20, 'gross' => 8, 'net' => 2],
                            ]
                        ],
                        [
                            'groupId' => $grossCustomerGroupId,
                            'displayGross' => true,
                            'prices' => []
                        ]
                    ]
                ),
                'advancedPricingConfig' => 'unit',
                'expectedPrices' => [
                    $netCustomerGroupId => 3,
                    $grossCustomerGroupId => 15
                ]
            ],
            'Test unit config and no advanced prices' => [
                'groupsData' => $this->getAdvancedPricesTestGroupData(
                    [
                        ['groupId' => $netCustomerGroupId, 'displayGross' => false, 'prices' => []],
                        ['groupId' => $grossCustomerGroupId, 'displayGross' => true, 'prices' => []]
                    ]
                ),
                'advancedPricingConfig' => 'unit',
                'expectedPrices' => [
                    $netCustomerGroupId => 10,
                    $grossCustomerGroupId => 15
                ]
            ],
        ];
    }

    /**
     * @dataProvider advancedPricesTestProvider
     */
    public function testIsCorrectAdvancedPriceIsExported(
        array $groupsData,
        string $advancedPricingConfig,
        array $expectedPrices
    ): void {
        if (Utils::versionLowerThan('6.4.9.0')) {
            $this->markTestSkipped('Advanced price calculation by Product entity exists in newer Shopware versions');
        }

        /** @var ExportContext $exportContext */
        $exportContext = $this->getContainer()->get('fin_search.export_context');

        $config = $this->getFindologicConfig(['advancedPricing' => $advancedPricingConfig]);
        $config->initializeBySalesChannel($this->salesChannelContext);
        $configShopkey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';
        $this->enableFindologicPlugin($this->getContainer(), $configShopkey, $this->salesChannelContext);

        $adapter = new PriceAdapter(
            $this->salesChannelContext,
            $this->exportContext,
            $this->getContainer()->get(ProductPriceCalculator::class),
            $this->getContainer()->get(CustomerGroupContextProvider::class),
            $config,
            '6.4.9.0'
        );

        $customerGroups = $this->generateCustomers($groupsData);
        $exportContext->setCustomerGroups($customerGroups);
        $this->createRules($groupsData);
        $productEntity = $this->createTestProduct(['prices' => $this->getPrices($groupsData)]);
        $this->createCustomerGroups($groupsData);
        $this->createCustomers($groupsData);

        $prices = $adapter->adapt($productEntity);

        foreach ($customerGroups as $customerGroup) {
            $userGroup = Utils::calculateUserGroupHash($exportContext->getShopkey(), $customerGroup->getId());

            foreach ($prices as $price) {
                foreach ($price->getValues() as $group => $value) {
                    if ($userGroup === $group) {
                        $this->assertEquals($expectedPrices[$customerGroup->getId()], $value);
                    }
                }
            }
        }
    }
}
