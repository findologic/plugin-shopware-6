<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\FinSearch\Export\Adapters\PriceAdapter;
use FINDOLOGIC\FinSearch\Export\Providers\CustomerGroupContextProvider;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\AdvancedPriceHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ServicesHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PriceAdapterTest extends TestCase
{
    use AdvancedPriceHelper;
    use ConfigHelper;
    use IntegrationTestBehaviour;
    use PluginConfigHelper;
    use ProductHelper;
    use SalesChannelHelper;
    use ServicesHelper;

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    protected SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
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

        $customerGroups = $this->generateCustomers($groupsData);
        $exportContext = $this->getExportContext(
            $this->salesChannelContext,
            $this->getCategory($this->salesChannelContext->getSalesChannel()->getNavigationCategoryId()),
            $customerGroups
        );

        $config = $this->getPluginConfig(['advancedPricing' => $advancedPricingConfig]);
        $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $this->salesChannelContext);

        $adapter = new PriceAdapter(
            $exportContext,
            $this->salesChannelContext,
            $this->getContainer()->get(ProductPriceCalculator::class),
            $this->getContainer()->get(CustomerGroupContextProvider::class),
            $this->getContainer()->get('sales_channel.product.repository'),
            $config,
            '6.4.9.0'
        );

        $this->createRules($groupsData);
        $productEntity = $this->createTestProduct(['prices' => $this->getPrices($groupsData)]);
        $this->createCustomerGroups($groupsData);
        $this->createCustomersForGroups($groupsData);

        $prices = $adapter->adapt($productEntity);

        foreach ($customerGroups as $customerGroup) {
            $userGroup = $customerGroup->id;

            foreach ($prices as $price) {
                foreach ($price->getValues() as $group => $value) {
                    if ($userGroup === $group) {
                        $this->assertEquals($expectedPrices[$customerGroup->id], $value);
                    }
                }
            }
        }
    }
}
