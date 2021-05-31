<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class DynamicProductGroupService
{
    public const CONTAINER_ID = 'fin_search.dynamic_product_group';
    private const CACHE_ID_PRODUCT_GROUP = 'fl_product_groups';
    private const CACHE_LIFETIME_PRODUCT_GROUP = 60 * 11;

    /**
     * @var ProductStreamBuilderInterface
     */
    protected $productStreamBuilder;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $shopkey;

    /**
     * @var int
     */
    protected $start;

    /**
     * @var int
     */
    protected $count;

    /**
     * @var int
     */
    protected $total;

    /**
     * @var SalesChannelEntity
     */
    private $salesChannel;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    private function __construct(
        ContainerInterface $container,
        CacheItemPoolInterface $cache,
        Context $context,
        string $shopkey,
        int $start,
        int $count
    ) {
        $this->container = $container;
        $this->cache = $cache;
        $this->shopkey = $shopkey;
        $this->start = $start;
        $this->count = $count;
        $this->productStreamBuilder = $container->get(ProductStreamBuilder::class);
        $this->productRepository = $container->get('product.repository');
        $this->categoryRepository = $container->get('category.repository');
        $this->context = $context;
    }

    public static function getInstance(
        ContainerInterface $container,
        CacheItemPoolInterface $cache,
        Context $context,
        string $shopkey,
        int $start,
        int $count
    ): DynamicProductGroupService {
        if ($container->has(self::CONTAINER_ID)) {
            $dynamicProductGroupService = $container->get(self::CONTAINER_ID);
        } else {
            $dynamicProductGroupService = new DynamicProductGroupService(
                $container,
                $cache,
                $context,
                $shopkey,
                $start,
                $count
            );
            $container->set(self::CONTAINER_ID, $dynamicProductGroupService);
        }

        return $dynamicProductGroupService;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannelEntity): void
    {
        $this->salesChannel = $salesChannelEntity;
    }

    public function warmUp(): void
    {
        $products = $this->parseProductGroups();
        if (Utils::isEmpty($products)) {
            $this->setDynamicProductGroupWarmedUpFlag();

            return;
        }

        // Set the total dynamic groups in cache once during warm up
        $totalCacheItem = $this->getDynamicGroupsTotalCacheItem();
        if ($totalCacheItem && !$totalCacheItem->isHit()) {
            $total = $this->getTotalDynamicProductGroupsCount();
            $this->setTotalInCache($totalCacheItem, $total);
        }

        $cacheItem = $this->getExportPaginationCacheItem();
        $cacheItem->set($products);
        $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
        $this->cache->save($cacheItem);
    }

    public function isWarmedUp(): bool
    {
        if ($this->start > 0) {
            $cacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();
            if ($cacheItem && $cacheItem->isHit()) {
                $cacheItem->set(true);
                $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
                $this->cache->save($cacheItem);

                return true;
            }
        }

        return false;
    }

    private function parseProductGroups(): array
    {
        $criteria = $this->buildCriteria();
        $criteria->setOffset($this->start)->setLimit($this->count);

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $this->context)->getEntities();

        if ($categories === null || !$categories->count()) {
            return [];
        }

        $products = [];
        /** @var CategoryEntity $categoryEntity */
        foreach ($categories as $categoryEntity) {
            $productStream = $categoryEntity->getProductStream();

            if (!$productStream) {
                continue;
            }

            $filters = $this->productStreamBuilder->buildFilters(
                $productStream->getId(),
                $this->context
            );

            $criteria = new Criteria();
            $criteria->addFilter(...$filters);
            /** @var string[] $productIds */
            $productIds = $this->productRepository->searchIds($criteria, $this->context)->getIds();
            foreach ($productIds as $productId) {
                $products[$productId][] = $categoryEntity->getId();
            }
        }

        return $products;
    }

    /**
     * @return CategoryEntity[]
     */
    public function getCategories(string $productId): array
    {
        $categories = [];
        $cacheItem = $this->getExportPaginationCacheItem();
        if ($cacheItem->isHit()) {
            $categories = $cacheItem->get();
        }

        if (!Utils::isEmpty($categories) && isset($categories[$productId])) {
            $categoryIds = $categories[$productId];
            $criteria = $this->buildCriteria();
            $criteria->setIds($categoryIds);

            return $this->categoryRepository->search($criteria, $this->context)->getElements();
        }

        return [];
    }

    private function buildCriteria(): Criteria
    {
        $mainCategoryId = $this->salesChannel->getNavigationCategoryId();

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('path', $mainCategoryId));
        $criteria->addAssociation('seoUrls');
        $criteria->addAssociation('productStream');
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('productStreamId', null)]
            )
        );

        return $criteria;
    }

    private function getExportPaginationCacheItem(): CacheItemInterface
    {
        $id = sprintf('%s_%s_%s', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey, $this->start);

        return $this->cache->getItem($id);
    }

    private function setTotalInCache(CacheItemInterface $cacheItem, $total): void
    {
        $cacheItem->set($total);
        $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
        $this->cache->save($cacheItem);
    }

    private function getDynamicGroupsTotalCacheItem(): CacheItemInterface
    {
        $id = sprintf('%s_%s_total', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey);

        return $this->cache->getItem($id);
    }

    /**
     * Gets the total count of dynamic product groups from cache
     */
    public function getDynamicProductGroupTotalFromCache(): int
    {
        $cacheItem = $this->getDynamicGroupsTotalCacheItem();
        if ($cacheItem && $cacheItem->isHit()) {
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return (int)$cacheItem->get();
        }

        return 0;
    }

    /**
     * If we have reached the last page of the dynamic product group export, we set a flag in cache to
     * know that the dynamic product groups are warmed up
     */
    public function setDynamicProductGroupWarmedUpFlag(int $total = 0): void
    {
        if (($this->start + $this->count) >= $total) {
            $cacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();
            if ($cacheItem) {
                $cacheItem->set(true);
                $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
                $this->cache->save($cacheItem);
            }
        }
    }

    /**
     * Gets the total count of available dynamic product groups to be exported
     */
    private function getTotalDynamicProductGroupsCount(): int
    {
        $total = 0;
        $criteria = $this->buildCriteria();
        $categories = $this->getCategoriesWithProductGroups($criteria);
        foreach ($categories as $categoryEntity) {
            $productStream = $categoryEntity->getProductStream();
            if (!$productStream) {
                continue;
            }

            $total++;
        }

        return $total;
    }

    private function getCategoriesWithProductGroups(Criteria $criteria): ?CategoryCollection
    {
        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $this->context)->getEntities();

        if ($categories === null || !$categories->count()) {
            return null;
        }

        return $categories;
    }

    public function getDynamicProductGroupWarmedUpCacheItem(): CacheItemInterface
    {
        $id = sprintf('%s_%s_dpg_warmup', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey);

        return $this->cache->getItem($id);
    }

    public function isDynamicProductGroupWarmedUp(): bool
    {
        if (Utils::versionLowerThan('6.3.1.0')) {
            return true;
        }

        $cacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();
        if ($cacheItem && $cacheItem->isHit()) {
            return (bool)$cacheItem->get();
        }

        return false;
    }
}
