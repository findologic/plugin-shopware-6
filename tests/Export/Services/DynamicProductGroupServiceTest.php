<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\Search\CategorySearcher;
use FINDOLOGIC\FinSearch\Export\Services\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ServicesHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Validation\ExportConfigurationBase;
use FINDOLOGIC\Shopware6Common\Export\Validation\OffsetExportConfiguration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Exception\CacheException;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryEntity;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupCollection;

class DynamicProductGroupServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ConfigHelper;
    use ProductHelper;
    use ServicesHelper;
    use SalesChannelHelper;

    protected SalesChannelContext $salesChannelContext;

    protected Context $defaultContext;

    protected string $validShopkey;

    protected string $cacheKey;

    /** @var CacheItemPoolInterface|MockObject */
    private $cache;

    private int $start;

    private ExportConfigurationBase $exportConfig;

    private ExportContext $exportContext;

    private CategorySearcher $categorySearcher;

    private ?string $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildAndCreateSalesChannelContext();
        $this->cache = $this->getMockBuilder(CacheItemPoolInterface::class)->disableOriginalConstructor()->getMock();
        $this->start = 0;
        $this->productId = null;
        $this->defaultContext = Context::createDefaultContext();
        $this->validShopkey = $this->getShopkey();
        $this->createTestProductStreams();
        $this->exportConfig = new OffsetExportConfiguration($this->validShopkey, 0, 100);
        $this->exportContext = $this->getExportContext(
            $this->salesChannelContext,
            $this->getCategory($this->salesChannelContext->getSalesChannel()->getNavigationCategoryId()),
            new CustomerGroupCollection(),
            $this->validShopkey
        );
        $this->categorySearcher = $this->getCategorySearcher(
            $this->salesChannelContext,
            $this->getContainer(),
            $this->exportContext
        );
    }

    public static function cacheWarmUpProvider(): array
    {
        return [
            'Cache is warmed up' => [
                'isWarmup' => true,
                'invokeCount' => static::atLeastOnce(),
            ],
            'Cache is not warmed up' => [
                'isWarmup' => false,
                'invokeCount' => static::never(),
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
            ->with($this->getCacheKeyByType('warmup'))
            ->willReturn($cacheItemMock);
        $this->cache->expects($invokeCount)->method('save')->with($cacheItemMock);

        $dynamicProductGroupService = $this->getDynamicProductGroupService();

        $this->assertSame($isWarmup, $dynamicProductGroupService->areDynamicProductGroupsCached());
    }

    public function testCategoriesAreCached(): void
    {
        $productStreams = [];
        $context = $this->salesChannelContext->getContext();
        [$categoryOne, $categoryTwo] = $this->getProductStreamCategories($context);
        $unknownStreamId = Uuid::randomHex();

        $productStreams[$categoryOne->productStreamId] = [$categoryOne];
        $productStreams[$categoryTwo->productStreamId] = [$categoryTwo];

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();

        $cacheItemMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls([$categoryOne], [$categoryTwo]);
        $cacheItemMock->expects($this->exactly(3))
            ->method('isHit')
            ->willReturnOnConsecutiveCalls(true, true, false);
        $cacheItemMock->expects($this->never())->method('set');

        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->withConsecutive(
                [$this->getCacheKeyByType('streamId', $categoryOne->productStreamId)],
                [$this->getCacheKeyByType('streamId', $categoryTwo->productStreamId)],
                [$this->getCacheKeyByType('streamId', $unknownStreamId)],
            )
            ->willReturn($cacheItemMock);

        $dynamicService = $this->getDynamicProductGroupService();

        $streamOneCategories = $dynamicService->getCategories($categoryOne->productStreamId);
        $streamOTwoCategories = $dynamicService->getCategories($categoryTwo->productStreamId);
        $unknownStreamCategories = $dynamicService->getCategories($unknownStreamId);
        $this->assertCount(1, $streamOneCategories);
        $this->assertCount(1, $streamOTwoCategories);
        $this->assertEmpty($unknownStreamCategories);
    }

    private function createTestProductStreams(): void
    {
        $streamId = Uuid::randomHex();
        $navigationCategoryId = $this->salesChannelContext->getSalesChannel()->getNavigationCategoryId();
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
            $this->createTestCategory([
                'productStreamId' => md5($streamId . $i),
                'name' => 'Findologic Xtream ' . $i,
                'parentId' => $navigationCategoryId,
                'productAssignmentType' => 'product_stream'
            ]);
        }
    }

    private function getDynamicProductGroupService(): DynamicProductGroupService
    {
        return new DynamicProductGroupService(
            $this->getContainer()->get('product.repository'),
            $this->salesChannelContext,
            $this->exportConfig,
            $this->cache,
            $this->exportContext,
            $this->categorySearcher,
        );
    }

    private function createProducts(): array
    {
        $productRepository = $this->getContainer()->get('product.repository');
        $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
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

    /**
     * @return array<int, CategoryEntity>
     */
    private function getProductStreamCategories(Context $context): array
    {
        $categoryRepository = $this->getContainer()->get('category.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Findologic Xtream 1'));
        $categoryOne = Utils::createSdkEntity(
            CategoryEntity::class,
            $categoryRepository->search($criteria, $context)->first(),
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Findologic Xtream 2'));
        $categoryTwo = Utils::createSdkEntity(
            CategoryEntity::class,
            $categoryRepository->search($criteria, $context)->first(),
        );

        return [$categoryOne, $categoryTwo];
    }

    private function getCacheKeyByType(string $type, ?string $value = null): string
    {
        switch ($type) {
            case 'streamId':
                return sprintf('fl_product_groups_%s_%s', $this->validShopkey, $value);
            case 'total':
                return sprintf('fl_product_groups_%s_total', $this->validShopkey);
            case 'warmup':
                return sprintf('fl_product_groups_%s_dynamic_product_warmup', $this->validShopkey);
            default:
                throw new CacheException('Unknown cache type requested');
        }
    }
}
