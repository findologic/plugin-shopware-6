<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Search;

use FINDOLOGIC\FinSearch\Export\Search\ProductStreamSearcher;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductStreamSearcherTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;
    use SalesChannelHelper;

    protected ProductStreamSearcher $productStreamSearcher;

    private SalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildAndCreateSalesChannelContext();
    }

    public function testFiltersAreCached(): void
    {
        $product = $this->createTestProduct();

        $streamId = Uuid::randomHex();
        $cacheKey = 'finsearch_stream_filters_' . $streamId;
        $expectedFilters = [
            new EqualsFilter('id', $product->id)
        ];

        $productStreamBuilder = $this->getMockBuilder(ProductStreamBuilder::class)
            ->onlyMethods(['buildFilters'])
            ->disableOriginalConstructor()
            ->getMock();
        $productStreamBuilder->method('buildFilters')
            ->willReturn($expectedFilters);

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cacheItemMock->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItemMock->expects($this->once())
            ->method('set')
            ->with(serialize($expectedFilters));
        $cacheItemMock->expects($this->never())
            ->method('get');

        $cachePoolMock->expects($this->exactly(2))
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);
        $cachePoolMock->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $productStreamSearcher = new ProductStreamSearcher(
            $productStreamBuilder,
            $this->salesChannelContext,
            $this->getContainer()->get('sales_channel.product.repository'),
            $cachePoolMock,
        );

        $this->assertTrue($productStreamSearcher->isProductInDynamicProductGroup($product->id, $streamId));
    }

    public function testFiltersAreFetchedFromCache(): void
    {
        $product = $this->createTestProduct();
        $streamId = Uuid::randomHex();
        $cacheKey = 'finsearch_stream_filters_' . $streamId;
        $expectedFilters = [
            new EqualsFilter('id', $product->id)
        ];

        $productStreamBuilder = $this->getMockBuilder(ProductStreamBuilder::class)
            ->onlyMethods(['buildFilters'])
            ->disableOriginalConstructor()
            ->getMock();
        $productStreamBuilder->expects($this->never())->method('buildFilters');

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cacheItemMock->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItemMock->expects($this->once())
            ->method('get')
            ->willReturn(serialize($expectedFilters));
        $cacheItemMock->expects($this->never())
            ->method('set');

        $cachePoolMock->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);
        $cachePoolMock->expects($this->never())
            ->method('save')
            ->with($cacheItemMock);

        $productStreamSearcher = new ProductStreamSearcher(
            $productStreamBuilder,
            $this->salesChannelContext,
            $this->getContainer()->get('sales_channel.product.repository'),
            $cachePoolMock,
        );

        $this->assertTrue($productStreamSearcher->isProductInDynamicProductGroup($product->id, $streamId));
    }
}
