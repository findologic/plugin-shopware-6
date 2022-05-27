<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\Export\Data\Ordernumber;
use FINDOLOGIC\FinSearch\Export\Adapters\OrderNumberAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderNumbersAdapterTest extends TestCase
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
    }

    public function testOrderNumberContainsTheOrderNumberOfTheProduct(): void
    {
        $id = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $variantProductNumber = Uuid::randomHex();
        $expectedOrderNumbers = [
            new Ordernumber('FINDOLOGIC001'),
            new Ordernumber('FL001'),
            new Ordernumber('MAN001'),
            new Ordernumber($variantProductNumber),
            new Ordernumber('childEan'),
            new Ordernumber('MAN002'),
        ];

        $product = $this->createTestProduct([
            'id' => $id
        ]);

        $variantProduct = $this->createTestProduct([
           'id' => $variantId,
            'parentId' => $id,
            'productNumber' => $variantProductNumber,
            'ean' => 'childEan',
            'manufacturerNumber' => 'MAN002'
        ]);

        $adapter = $this->getContainer()->get(OrderNumberAdapter::class);
        $parentOrderNumbers = $adapter->adapt($product);
        $variantOrderNumbers = $adapter->adapt($variantProduct);

        $orderNumbers = array_merge($parentOrderNumbers, $variantOrderNumbers);
        $this->assertEquals($expectedOrderNumbers, $orderNumbers);
    }
}
