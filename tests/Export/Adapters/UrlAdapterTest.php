<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Adapters\Export;

use FINDOLOGIC\FinSearch\Export\Adapters\UrlAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UrlAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
    }

    public function testUrlContainsTheUrlOfTheProduct(): void
    {
        $expectedUrl = 'http://test.uk/FINDOLOGIC-Product/FINDOLOGIC001';

        $adapter = $this->getContainer()->get(UrlAdapter::class);
        $product = $this->createTestProduct();

        $url = $adapter->adapt($product);

        $this->assertCount(1, $url->getValues());
        $this->assertSame($expectedUrl, $url->getValues()['']);
    }
}
