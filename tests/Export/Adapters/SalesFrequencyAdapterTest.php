<?php

declare(strict_types = 1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use DateTimeImmutable;
use FINDOLOGIC\FinSearch\Export\Adapters\SalesFrequencyAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\OrderHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\RandomIdHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesFrequencyAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use OrderHelper;
    use RandomIdHelper;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var TestDataCollection */
    private $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
    }

    public function testSalesFrequencyContainsTheSalesFrequencyOfTheProduct(): void
    {
        $adapter = $this->getContainer()->get(SalesFrequencyAdapter::class);
        $product = $this->createTestProduct();
        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);
        $this->createOrder(
            $customerId,
            [
                'orderDateTime' => (new DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'lineItems' => [
                    $this->buildOrderLineItem(['productId' => $product->getId()])
                ],
            ]
        );

        $salesFrequency = $adapter->adapt($product);

        $this->assertSame(1, $salesFrequency->getValues()['']);
    }
}
