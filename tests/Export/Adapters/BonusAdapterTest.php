<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\FinSearch\Export\Adapters\BonusAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BonusAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;

    protected SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function testNullIsReturned(): void
    {
        $adapter = $this->getContainer()->get(BonusAdapter::class);
        $product = $this->createTestProduct();

        $this->assertNull($adapter->adapt($product));
    }
}
