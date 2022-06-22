<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\FinSearch\Export\Adapters\ShopwarePropertiesAdapter;
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

class ShopwarePropertiesAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use PropertiesHelper;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
    }

    public function testNonFilterablePropertiesAreExportedAsPropertiesInsteadOfAttributes(): void
    {
        if (Utils::versionLowerThan('6.2.0')) {
            $this->markTestSkipped('Properties can only have a filter visibility with Shopware 6.2.x and upwards');
        }

        $expectedPropertyName1 = 'blub';
        $expectedPropertyName2 = 'blub1';
        $expectedPropertyName3 = 'blub2';
        $expectedPropertyValue1 = 'some value';
        $expectedPropertyValue2 = 'some value1';
        $expectedPropertyValue3 = 'some value2';

        $expectedPropertiesNames = [
            $expectedPropertyName1,
            $expectedPropertyName2
        ];

        $productEntity = $this->createTestProduct(
            [
                'properties' => [
                    [
                        'id' => Uuid::randomHex(),
                        'name' => $expectedPropertyValue1,
                        'group' => [
                            'id' => Uuid::randomHex(),
                            'name' => $expectedPropertyName1,
                            'filterable' => false
                        ],
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'name' => $expectedPropertyValue2,
                        'group' => [
                            'id' => Uuid::randomHex(),
                            'name' => $expectedPropertyName2,
                            'filterable' => false
                        ],
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'name' => $expectedPropertyValue3,
                        'group' => [
                            'id' => Uuid::randomHex(),
                            'name' => $expectedPropertyName3,
                            'filterable' => true
                        ],
                    ]
                ]
            ]
        );

        $adapter = $this->getContainer()->get(ShopwarePropertiesAdapter::class);

        $properties = array_merge(
            $adapter->adapt($productEntity)
        );

        $foundProperties = array_filter(
            $properties,
            static function (Property $property) use ($expectedPropertiesNames) {
                return in_array($property->getKey(), $expectedPropertiesNames);
            }
        );
        $foundPropertyValues = array_map(
            static function (Property $property) {
                return $property->getAllValues()[''];
            },
            array_values($foundProperties)
        );

        $this->assertCount(2, $foundProperties);
        $this->assertContains($expectedPropertyValue1, $foundPropertyValues);
        $this->assertContains($expectedPropertyValue2, $foundPropertyValues);
    }
}
