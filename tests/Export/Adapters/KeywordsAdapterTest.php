<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\FinSearch\Export\Adapters\KeywordsAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class KeywordsAdapterTest extends TestCase
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

    public function testKeywordsContainsTheKeywordsOfTheProduct(): void
    {
        $expectedKeywords = new Keyword('FINDOLOGIC Tag 1');

        $adapter = $this->getContainer()->get(KeywordsAdapter::class);
        $product = $this->createTestProduct([
            'tags' =>  [
                ['id' => Uuid::randomHex(), 'name' => 'FINDOLOGIC Tag 1'],
            ]
        ]);

        $keywords = $adapter->adapt($product);

        $this->assertCount(1, $keywords);
        $this->assertEquals([$expectedKeywords], $keywords);
    }
}
