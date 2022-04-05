<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\Adapters\PropertiesAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PropertiesHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PropertiesAdapterTest extends TestCase
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

    public function testPropertiesContainsThePropertiesOfTheProduct(): void
    {
        $adapter = $this->getContainer()->get(PropertiesAdapter::class);
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
}
