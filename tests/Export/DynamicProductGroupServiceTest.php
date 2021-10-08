<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\DynamicProductGroupCacheHandler;
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
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Exception\CacheException;

class DynamicProductGroupServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ConfigHelper;
    use ExportHelper;
    use ProductHelper;

    /** @var Context */
    protected $defaultContext;

    /** @var string */
    protected $validShopkey;

    /** @var MockObject|CacheItemPoolInterface */
    private $cache;

    /** @var ContainerInterface */
    private $containerMock;

    /** @var int */
    private $start;

    /** @var int */
    private $total;

    /** @var string */
    private $productId;

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
        $this->total = 100;
        $this->defaultContext = Context::createDefaultContext();
        $this->validShopkey = $this->getShopkey();
        $this->createTestProductStreams();
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
        $cacheItemMock->expects($invokeCount)->method('get')->willReturn([]);
        $cacheItemMock->expects($invokeCount)->method('expiresAfter')->with(60 * 11);
        $cacheItemMock->expects($this->once())->method('isHit')->willReturn($isWarmup);
        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->getCacheKeyByType('offset'))
            ->willReturn($cacheItemMock);
        $this->cache->expects($invokeCount)->method('save')->with($cacheItemMock);

        $dynamicProductGroupService = $this->getDynamicProductGroupService();

        $this->assertSame($isWarmup, $dynamicProductGroupService->isCurrentOffsetWarmedUp());
    }

    public function testCategoriesAreCached(): void
    {
        $products = [];
        $salesChannelContext = $this->getDefaultSalesChannelContextMock();
        $context = $salesChannelContext->getContext();
        $salesChannel = $salesChannelContext->getSalesChannel();
        [$categoryOne, $categoryTwo] = $this->getProductStreamCategories($context);

        $productId = Uuid::randomHex();
        $products[$productId] = [$categoryOne->getId(), $categoryTwo->getId()];

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();

        $cacheItemMock->expects($this->once())->method('get')->willReturn($products);
        $cacheItemMock->expects($this->once())->method('isHit')->willReturn(true);
        $cacheItemMock->expects($this->never())->method('set');
        $cacheItemMock->expects($this->never())->method('expiresAfter')->with(60 * 11);

        $this->cache->expects($this->never())->method('save')->with($cacheItemMock);
        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->getCacheKeyByType('offset'))
            ->willReturn($cacheItemMock);

        $dynamicService = $this->getDynamicProductGroupService();
        $dynamicService->setSalesChannel($salesChannel);
        $categories = $dynamicService->getCategories($productId);
        $this->assertNotEmpty($categories);
        $this->assertCount(2, $categories);
    }

    public function testNoCategoriesAreReturnedForUnAssignedProduct(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContextMock();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $context = $salesChannelContext->getContext();
        [$categoryOne, $categoryTwo] = $this->getProductStreamCategories($context);
        $unassignedProductId = Uuid::randomHex();
        $products = [];
        $products[$this->productId] = [$categoryOne->getId(), $categoryTwo->getId()];

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->once())->method('get')->willReturn($products);
        $cacheItemMock->expects($this->once())->method('isHit')->willReturn(true);
        $cacheItemMock->expects($this->never())->method('set');
        $cacheItemMock->expects($this->never())->method('expiresAfter')->with(60 * 11);

        $this->cache->expects($this->never())->method('save')->with($cacheItemMock);
        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->getCacheKeyByType('offset'))
            ->willReturn($cacheItemMock);

        $dynamicService = $this->getDynamicProductGroupService();
        $dynamicService->setSalesChannel($salesChannel);
        $categories = $dynamicService->getCategories($unassignedProductId);
        $this->assertEmpty($categories);
    }

    public function testCategoriesAreNotCached(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContextMock();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $cacheOffsetKey = $this->getCacheKeyByType('offset');
        $cacheTotalKey = $this->getCacheKeyByType('total');

        $context = $salesChannelContext->getContext();
        [$categoryOne, $categoryTwo] = $this->getProductStreamCategories($context);
        $products = [];
        $products[$this->productId] = [$categoryOne->getId(), $categoryTwo->getId()];

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->once())->method('get')->willReturn($products);
        $cacheItemMock->expects($this->exactly(3))->method('set');
        $cacheItemMock->expects($this->exactly(3))->method('isHit')->willReturnOnConsecutiveCalls(false, false, true);
        $cacheItemMock->expects($this->exactly(3))->method('expiresAfter')->with(60 * 11);
        $this->cache->expects($this->exactly(3))->method('save')->with($cacheItemMock);
        $this->cache->expects($this->exactly(6))
            ->method('getItem')
            ->withConsecutive(
                [$cacheOffsetKey],
                [$cacheTotalKey],
                [$cacheTotalKey],
                [$cacheOffsetKey]
            )->willReturn($cacheItemMock);

        $dynamicService = $this->getDynamicProductGroupService();
        $dynamicService->setSalesChannel($salesChannel);
        if (!$dynamicService->isCurrentOffsetWarmedUp()) {
            $dynamicService->warmUp();
        }
        $categories = $dynamicService->getCategories($this->productId);
        $this->assertNotEmpty($categories);
    }

    private function createTestProductStreams(): void
    {
        $streamId = Uuid::randomHex();
        $navigationCategoryId = $this->getNavigationCategoryId();
        $randomProductIds = implode('|', array_column($this->createProducts(), 'id'));

        // Create multple streams with similar products to test multiple categories are assigned
        for ($i = 1; $i <= 2; $i++) {
            $stream[] = [
                'id' => md5($streamId . $i),
                'name' => 'testStream ' . $i,
                'filters' => [
                    [
                        'type' => 'equalsAny',
                        'field' => 'id',
                        'value' => $randomProductIds,
                    ],
                ],
            ];

            $productRepository = $this->getContainer()->get('product_stream.repository');
            $productRepository->upsert($stream, $this->defaultContext);

            // Assign each product stream to different categories
            $this->getContainer()->get('category.repository')->create(
                [
                    [
                        'id' => Uuid::randomHex(),
                        'productStreamId' => md5($streamId . $i),
                        'name' => 'Findologic Xtream ' . $i,
                        'parentId' => $navigationCategoryId,
                        'productAssignmentType' => 'product_stream'
                    ]
                ],
                Context::createDefaultContext()
            );
        }
    }

    private function getDynamicProductGroupService(): DynamicProductGroupService
    {
        $cacheHandler = new DynamicProductGroupCacheHandler($this->cache);

        return DynamicProductGroupService::getInstance(
            $this->containerMock,
            $cacheHandler,
            $this->defaultContext,
            $this->validShopkey,
            $this->start,
            $this->total
        );
    }

    private function createProducts(): array
    {
        $productRepository = $this->getContainer()->get('product.repository');
        $salesChannelId = Defaults::SALES_CHANNEL;
        $products = [];

        $names = [
            'Wooden Heavy Magma',
            'Small Plastic Prawn Leather',
            'Fantastic Marble Megahurts',
            'Foo Bar Aerodynamic Iron Viagreat',
            'Foo Bar Awesome Bronze Sulpha Quik',
            'Foo Bar Aerodynamic Silk Ideoswitch',
            'Heavy Duty Wooden Magnina',
            'Incredible Wool Q-lean',
            'Heavy Duty Cotton Gristle Chips',
            'Heavy Steel Hot Magma',
        ];

        for ($i = 0; $i < 10; ++$i) {
            $id = Uuid::randomHex();
            $products[] = [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => $names[$i],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'FINDOLOGIC'],
                'tax' => ['name' => '9%', 'taxRate' => 9],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];

            if (!$this->productId) {
                $this->productId = $id;
            }
        }

        $productRepository->create($products, $this->defaultContext);

        return $products;
    }

    private function getProductStreamCategories(Context $context): array
    {
        $categoryRepository = $this->getContainer()->get('category.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Findologic Xtream 1'));
        $categoryOne = $categoryRepository->search($criteria, $context)->first();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Findologic Xtream 2'));
        $categoryTwo = $categoryRepository->search($criteria, $context)->first();

        return [$categoryOne, $categoryTwo];
    }

    private function getCacheKeyByType(string $type): string
    {
        switch ($type) {
            case 'offset':
                return sprintf('fl_product_groups_%s_%d', $this->validShopkey, $this->start);
            case 'total':
                return sprintf('fl_product_groups_%s_total', $this->validShopkey);
            case 'warmup':
                return sprintf('fl_product_groups_%s_dynamic_product_warmup', $this->validShopkey);
            default:
                throw new CacheException('Unknown cache type requested');
        }
    }
}
