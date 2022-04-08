<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\Export\Exceptions\EmptyValueNotAllowedException;
use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Adapters\DescriptionAdapter;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DescriptionAdapterTest extends TestCase
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

    public function testDescriptionContainsTheDescriptionOfTheProduct(): void
    {
        $expectedDescription = 'FINDOLOGIC Description';

        $adapter = $this->getContainer()->get(DescriptionAdapter::class);
        $product = $this->createTestProduct();

        $description = $adapter->adapt($product);

        $this->assertCount(1, $description->getValues());
        $this->assertSame($expectedDescription, $description->getValues()['']);
    }

    /**
     * @dataProvider emptyValuesProvider
     */
    public function testEmptyDescriptionValuesAreSkipped(?string $value): void
    {
        $data = [
            'description' => $value,
        ];

        $productEntity = $this->createTestProduct($data);
        $adapter = $this->getContainer()->get(DescriptionAdapter::class);
        $description = $adapter->adapt($productEntity);

        $this->assertNull($description);
    }

    public function emptyValuesProvider(): array
    {
        return [
            'null values provided' => ['value' => null],
            'empty string values provided' => ['value' => ''],
            'values containing empty spaces provided' => ['value' => '    '],
        ];
    }
}
