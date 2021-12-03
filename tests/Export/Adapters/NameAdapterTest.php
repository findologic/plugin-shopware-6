<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Export\Adapters\NameAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NameAdapterTest extends TestCase
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

    public function testExceptionIsThrownIfNameOnlyContainsWhiteSpaces(): void
    {
        $this->expectException(ProductHasNoNameException::class);

        $adapter = $this->getContainer()->get(NameAdapter::class);
        $product = $this->createTestProduct([
            'name' => "\n\t\n\t\r"
        ]);

        $adapter->adapt($product);
    }

    public function testNameContainsTheNameOfTheProduct(): void
    {
        $expectedName = 'Best product that has ever existed';

        $adapter = $this->getContainer()->get(NameAdapter::class);
        $product = $this->createTestProduct([
            'name' => $expectedName
        ]);

        $name = $adapter->adapt($product);

        $this->assertCount(1, $name->getValues());
        $this->assertSame($expectedName, $name->getValues()['']);
    }
}
