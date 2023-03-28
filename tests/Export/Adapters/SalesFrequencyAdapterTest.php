<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use DateTimeImmutable;
use FINDOLOGIC\FinSearch\Export\Adapters\SalesFrequencyAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\OrderHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\RandomIdHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
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

    protected SalesChannelContext $salesChannelContext;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new TestDataCollection();
        $this->salesChannelContext = $this->buildAndCreateSalesChannelContext();
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
                    $this->buildOrderLineItem(['productId' => $product->id])
                ],
            ]
        );

        $salesFrequency = $adapter->adapt($product);

        $this->assertSame(1, $salesFrequency->getValues()['']);
    }

    public function testUsesMemoryEfficientWayToFetchSalesFrequency(): void
    {
        $expectedSalesFrequency = 1337;

        $productEntity = $this->createTestProduct();

        $orderLineItemRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $searchResultMock = $this->getMockBuilder(IdSearchResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Ensure only memory efficient calls are being made.
        $orderLineItemRepositoryMock->expects($this->once())->method('searchIds')
            ->willReturn($searchResultMock);
        $orderLineItemRepositoryMock->expects($this->never())->method('search');
        $searchResultMock->expects($this->once())->method('getTotal')->willReturn($expectedSalesFrequency);

        $adapter = new SalesFrequencyAdapter(
            $orderLineItemRepositoryMock,
            $this->salesChannelContext
        );

        $actualSalesFrequency = $adapter->adapt($productEntity);

        $this->assertSame($expectedSalesFrequency, $actualSalesFrequency->getValues()['']);
    }

    /**
     * @dataProvider salesFrequencyProvider
     */
    public function testSalesFrequencyIsBasedOnPreviousMonthsOrder(
        ?string $orderDateTime,
        int $expectedSalesFrequency
    ): void {
        $productEntity = $this->createTestProduct([
            'productNumber' => 'test'
        ]);

        $adapter = $this->getContainer()->get(SalesFrequencyAdapter::class);

        $customerId = Uuid::randomHex();
        if ($orderDateTime !== null) {
            $this->createCustomer($customerId);
            $this->createOrder(
                $customerId,
                [
                    'orderDateTime' => $orderDateTime,
                    'lineItems' => [
                        $this->buildOrderLineItem(['productId' => $productEntity->id])
                    ],
                ]
            );
        }

        $actualSalesFrequency = $adapter->adapt($productEntity);

        $this->assertSame($expectedSalesFrequency, $actualSalesFrequency->getValues()['']);
    }

    public static function salesFrequencyProvider(): array
    {
        return [
            'Product with order in the last 30 days' => [
                'orderDateTime' => (new DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'expectedSalesFrequency' => 1
            ],
            'Product with no orders' => ['orderDate' => null, 'expectedSalesFrequency' => 0],
            'Product with order older than 30 days' => [
                'orderDateTime' => (new DateTimeImmutable('2020-01-01'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'expectedSalesFrequency' => 0
            ],
        ];
    }
}
