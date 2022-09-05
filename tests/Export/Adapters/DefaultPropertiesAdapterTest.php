<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\FinSearch\Export\Adapters\DefaultPropertiesAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PropertiesHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DefaultPropertiesAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use PropertiesHelper;

    protected SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
    }

    public function testPropertiesContainsThePropertiesOfTheProduct(): void
    {
        $adapter = $this->getContainer()->get(DefaultPropertiesAdapter::class);
        $product = $this->createTestProduct([
            'weight' => 50,
            'width' => 8,
            'height' => 8,
            'length' => 20
        ]);

        $expectedProperties = $this->getProperties($product);
        $actualProperties = $adapter->adapt($product);

        $this->assertEquals($expectedProperties, $actualProperties);
    }

    /**
     * @dataProvider productPromotionProvider
     */
    public function testProductPromotionIsExported(?bool $markAsTopSeller, string $expected): void
    {
        $productEntity = $this->createTestProduct(['markAsTopseller' => $markAsTopSeller], true);
        $adapter = $this->getContainer()->get(DefaultPropertiesAdapter::class);
        $properties = $adapter->adapt($productEntity);

        $promotion = end($properties);
        $this->assertNotNull($promotion);
        $this->assertSame('product_promotion', $promotion->getKey());
        $values = $promotion->getAllValues();
        $this->assertNotEmpty($values);
        $this->assertSame($expected, current($values));
    }

    /**
     * @dataProvider listPriceProvider
     */
    public function testProductListPrice(?string $currencyId, bool $isPriceAvailable): void
    {
        if ($currencyId === null && Utils::versionGreaterOrEqual('6.4.2.0')) {
            $this->markTestSkipped(
                'SW >= 6.4.2.0 requires a price to be set for the default currency. Therefore not testable.'
            );
        }

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
                            'linked' => false,
                        ],
                    ]
                ],
            ]
        );
        $adapter = $this->getContainer()->get(DefaultPropertiesAdapter::class);
        $properties = $adapter->adapt($productEntity);

        $hasListPrice = false;
        $hasListPriceNet = false;

        foreach ($properties as $property) {
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

    public function productPromotionProvider(): array
    {
        return [
            'Product has promotion set to false' => [false, 'finSearch.general.no'],
            'Product has promotion set to true' => [true, 'finSearch.general.yes'],
            'Product promotion is set to null' => [null, 'finSearch.general.no']
        ];
    }
}
