<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Adapters;

use FINDOLOGIC\FinSearch\Export\Adapters\NameAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Exceptions\Product\ProductHasNoNameException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NameAdapterTest extends TestCase
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

    public function testExceptionIsThrownIfNameOnlyContainsWhiteSpaces(): void
    {
        $this->expectException(ProductHasNoNameException::class);

        $adapter = $this->getContainer()->get(NameAdapter::class);
        $product = $this->createTestProduct();

        // Setting an empty name does not pass the builder validation
        $product->setName(null);
        $product->setTranslated([]);

        $adapter->adapt($product);
    }

    public function testNameContainsTheNameOfTheProduct(): void
    {
        $productName = 'Best product that has ever existed';
        $expectedName = Utils::versionGreaterOrEqual('6.4.11.0')
            ? 'Best product that has ever existed EN'
            : 'Best product that has ever existed';

        $adapter = $this->getContainer()->get(NameAdapter::class);
        $product = $this->createTestProduct([
            'name' => $productName
        ]);

        $name = $adapter->adapt($product);

        $this->assertCount(1, $name->getValues());
        $this->assertSame($expectedName, $name->getValues()['']);
    }
}
