<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Adapters\Export;

use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\FinSearch\Export\Adapters\KeywordsAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordCollection;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordEntity;
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
        $expectedKeywords = [new Keyword('keyword1'), new Keyword('keyword2')];
        $keywordsEntities = [$this->getKeywordEntity('keyword1'), $this->getKeywordEntity('keyword2')];
        $productSearchKeywordCollection = new ProductSearchKeywordCollection($keywordsEntities);

        $product = $this->createTestProduct();
        $product->setSearchKeywords($productSearchKeywordCollection);

        $adapter = $this->getContainer()->get(KeywordsAdapter::class);
        $keywords = $adapter->adapt($product);

        $this->assertCount(2, $keywords);
        $this->assertEquals($expectedKeywords, $keywords);
    }

    private function getKeywordEntity(string $keyword): ProductSearchKeywordEntity
    {
        $productSearchKeywordEntity = new ProductSearchKeywordEntity();
        $productSearchKeywordEntity->setId(Uuid::randomHex());
        $productSearchKeywordEntity->setKeyword($keyword);

        return $productSearchKeywordEntity;
    }
}
