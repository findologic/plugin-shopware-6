<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class DynamicProductGroupCacheHandler
{
    private const CACHE_ID_PRODUCT_GROUP = 'fl_product_groups';
    private const CACHE_LIFETIME_PRODUCT_GROUP = 60 * 11;

    /** @var CacheItemPoolInterface */
    protected $cache;

    /** @var string|null */
    protected $shopkey;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function setShopkey(string $shopkey): void
    {
        $this->shopkey = $shopkey;
    }

    public function warmUpDynamicProductGroups(int $start, int $count, int $total = 0): void
    {
        // If we have reached the last page of the dynamic product group export, we then set a flag in cache to
        // make sure that the dynamic product groups are all warmed up.
        if (($start + $count) >= $total) {
            $cacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();
            if ($cacheItem) {
                $cacheItem->set(true);
                $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
                $this->cache->save($cacheItem);
            }
        }
    }

    public function isDynamicProductGroupTotalCached(): bool
    {
        $totalCacheItem = $this->getDynamicGroupsTotalCacheItem();

        return $totalCacheItem && $totalCacheItem->isHit();
    }

    public function setDynamicProductGroupTotal(int $total): void
    {
        $totalCacheItem = $this->getDynamicGroupsTotalCacheItem();
        $this->setTotalInCache($totalCacheItem, $total);
    }

    /**
     * @param array<string, array<int, string>> $products
     */
    public function setDynamicProductGroupsOffset(array $products, int $offset): void
    {
        if ($offset > 0) {
            $cacheItem = $this->getDynamicProductGroupOffsetCacheItem($offset);
            $cacheItem->set($products);
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);
        }
    }

    public function isCacheWarmedUp(int $offset): bool
    {
        $cacheItem = $this->getDynamicProductGroupOffsetCacheItem($offset);
        if ($cacheItem && $cacheItem->isHit()) {
            $cacheItem->set(true);
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return true;
        }

        return false;
    }

    public function areDynamicProductGroupsCached(): bool
    {
        $cacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();
        if ($cacheItem && $cacheItem->isHit()) {
            $cacheItem->set(true);
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return true;
        }

        return false;
    }

    public function getCachedCategoryIdsForCurrentOffset(int $offset): array
    {
        $categories = [];
        $cacheItem = $this->getDynamicProductGroupOffsetCacheItem($offset);
        if ($cacheItem->isHit()) {
            $categories = (array)$cacheItem->get();
        }

        return $categories;
    }

    public function getDynamicProductGroupsCachedTotal(): int
    {
        return $this->getDynamicProductGroupTotalFromCache();
    }

    protected function getDynamicProductGroupWarmedUpCacheItem(): CacheItemInterface
    {
        $id = sprintf('%s_%s_dynamic_product_warmup', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey);

        return $this->cache->getItem($id);
    }

    protected function getDynamicProductGroupOffsetCacheItem(int $offset): CacheItemInterface
    {
        $id = sprintf('%s_%s_%s', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey, $offset);

        return $this->cache->getItem($id);
    }

    protected function setTotalInCache(CacheItemInterface $cacheItem, int $total): void
    {
        $cacheItem->set($total);
        $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
        $this->cache->save($cacheItem);
    }

    protected function getDynamicGroupsTotalCacheItem(): CacheItemInterface
    {
        $id = sprintf('%s_%s_total', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey);

        return $this->cache->getItem($id);
    }

    protected function getDynamicProductGroupTotalFromCache(): int
    {
        $cacheItem = $this->getDynamicGroupsTotalCacheItem();
        if ($cacheItem && $cacheItem->isHit()) {
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return $cacheItem->get();
        }

        return 0;
    }
}
