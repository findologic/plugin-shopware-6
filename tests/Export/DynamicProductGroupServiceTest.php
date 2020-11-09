<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ExportHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

use function serialize;

class DynamicProductGroupServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ConfigHelper;
    use ExportHelper;
    use ProductHelper;

    /**
     * @var Context
     */
    protected $defaultContext;

    /**
     * @var string
     */
    protected $validShopkey;

    /**
     * @var string
     */
    protected $cacheKey;

    /**
     * @var MockObject|CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var ContainerInterface
     */
    private $containerMock;

    /**
     * @var int
     */
    private $start;

    protected function setUp(): void
    {
        parent::setUp();
        if (Utils::versionLowerThan('6.3.1.0')) {
            $this->markTestSkipped('Product streams in category is not available until v6.3.1.0');
        }
        $this->cache = $this->getMockBuilder(CacheItemPoolInterface::class)->disableOriginalConstructor()->getMock();
        $services['product.repository'] = $this->getContainer()->get('product.repository');
        $this->containerMock = $this->getContainerMock($services);
        $this->start = 1;
        $this->defaultContext = Context::createDefaultContext();
        $this->validShopkey = $this->getShopkey();
        $this->cacheKey = sprintf('%s_%s', 'fl_product_groups', $this->validShopkey);
    }

    public function cacheWarmUpProvider(): array
    {
        return [
            'Cache is warmed up' => [
                'isWarmup' => true,
                'invokeCount' => $this->atLeastOnce(),
            ],
            'Cache is not warmed up' => [
                'isWarmup' => false,
                'invokeCount' => $this->never(),
            ],
        ];
    }

    /**
     * @dataProvider cacheWarmUpProvider
     */
    public function testCacheWarmUp(bool $isWarmup, $invokeCount): void
    {
        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($invokeCount)
            ->method('get')
            ->willReturn([]);
        $cacheItemMock->expects($invokeCount)
            ->method('expiresAfter')
            ->with(60 * 11);

        $cacheItemMock->expects($this->once())
            ->method('isHit')
            ->willReturn($isWarmup);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->cacheKey)
            ->willReturn($cacheItemMock);

        $this->cache->expects($invokeCount)->method('save')->with($cacheItemMock);

        $dynamicProductGroupService = $this->getDynamicProductGroupService();

        $this->assertSame($isWarmup, $dynamicProductGroupService->isWarmedUp());
    }

    public function testCategoriesAreCached(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContextMock();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $productId = Uuid::randomHex();
        $products = [];
        $products[$productId] = [new CategoryEntity(), new CategoryEntity()];

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->never())->method('set');
        $cacheItemMock->expects($this->once())->method('get')->willReturn(serialize($products));
        $cacheItemMock->expects($this->once())->method('expiresAfter')->with(60 * 11);
        $cacheItemMock->expects($this->exactly(2))->method('isHit')->willReturn(true);

        $this->cache->expects($this->once())->method('save')->with($cacheItemMock);
        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->with($this->cacheKey)
            ->willReturn($cacheItemMock);

        $dynamicService = $this->getDynamicProductGroupService();
        $dynamicService->setSalesChannel($salesChannel);
        if (!$dynamicService->isWarmedUp()) {
            $dynamicService->warmUp();
        }
        $categories = $dynamicService->getCategories($productId);
        $this->assertNotEmpty($categories);
        $this->assertCount(2, $categories);
    }

    public function testNoCategoriesAreReturnedForUnAssignedProduct(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContextMock();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $productId = Uuid::randomHex();
        $unassignedProductId = Uuid::randomHex();
        $products = [];
        $products[$productId] = [new CategoryEntity(), new CategoryEntity()];

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->never())->method('set');
        $cacheItemMock->expects($this->once())->method('get')->willReturn(serialize($products));
        $cacheItemMock->expects($this->once())->method('expiresAfter')->with(60 * 11);
        $cacheItemMock->expects($this->exactly(2))->method('isHit')->willReturn(true);

        $this->cache->expects($this->once())->method('save')->with($cacheItemMock);
        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->with($this->cacheKey)
            ->willReturn($cacheItemMock);

        $dynamicService = $this->getDynamicProductGroupService();
        $dynamicService->setSalesChannel($salesChannel);
        if (!$dynamicService->isWarmedUp()) {
            $dynamicService->warmUp();
        }
        $categories = $dynamicService->getCategories($unassignedProductId);
        $this->assertEmpty($categories);
    }

    public function testCategoriesAreNotCached(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContextMock();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $productId = Uuid::randomHex();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->once())->method('set');
        $cacheItemMock->expects($this->never())->method('get');
        $cacheItemMock->expects($this->once())->method('expiresAfter')->with(60 * 11);

        $this->cache->expects($this->once())->method('save')->with($cacheItemMock);
        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->with($this->cacheKey)
            ->willReturn($cacheItemMock);

        $dynamicService = $this->getDynamicProductGroupService();
        $dynamicService->setSalesChannel($salesChannel);
        if (!$dynamicService->isWarmedUp()) {
            $dynamicService->warmUp();
        }
        $categories = $dynamicService->getCategories($productId);
        $this->assertEmpty($categories);
    }

    private function getDynamicProductGroupService(): DynamicProductGroupService
    {
        return DynamicProductGroupService::getInstance(
            $this->containerMock,
            $this->cache,
            $this->defaultContext,
            $this->validShopkey,
            $this->start
        );
    }
}
