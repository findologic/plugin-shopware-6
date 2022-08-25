<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\Adapters\PriceAdapter;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Export\Provider\CustomerGroupSalesChannelProvider;
use FINDOLOGIC\FinSearch\Export\Provider\PriceBasedOnConfigurationProvider;
use FINDOLOGIC\FinSearch\Struct\Config;
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

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var ExportContext */
    protected $exportContext;

    /** @var ProductService */
    protected $productService;

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

        $this->productService = ProductService::getInstance($this->getContainer(), $this->salesChannelContext);
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
            $this->getContainer()->get(CustomerGroupSalesChannelProvider::class),
            $this->getContainer()->get(PriceBasedOnConfigurationProvider::class),
            $this->getContainer()->get(Config::class)
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
}
