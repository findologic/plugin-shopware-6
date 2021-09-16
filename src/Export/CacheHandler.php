<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class CacheHandler
{
    private const CACHE_ID_PRODUCT_GROUP = 'fl_product_groups';
    private const CACHE_LIFETIME_PRODUCT_GROUP = 60 * 11;

    /** @var CacheItemPoolInterface */
    protected $cache;

    /** @var string */
    protected $shopkey;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function setShopkey(string $shopkey): void
    {
        $this->shopkey = $shopkey;
    }

    /**
     * If we have reached the last page of the dynamic product group export, we set a flag in cache to
     * know that the dynamic product groups are warmed up.
     */
    public function dynamicProductGroupWarmUp(int $start, int $count, int $total = 0): void
    {
        if (($start + $count) >= $total) {
            $cacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();
            if ($cacheItem) {
                $cacheItem->set(true);
                $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
                $this->cache->save($cacheItem);
            }
        }
    }

    private function getDynamicProductGroupWarmedUpCacheItem(): CacheItemInterface
    {
        $id = sprintf('%s_%s_dynamic_product_warmup', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey);

        return $this->cache->getItem($id);
    }

    private function getDynamicProductGroupOffsetCacheItem(int $offset): CacheItemInterface
    {
        $id = sprintf('%s_%s_%s', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey, $offset);

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
     * Gets the total count of dynamic product groups from cache.
     */
    private function getDynamicProductGroupTotalFromCache(): int
    {
        $cacheItem = $this->getDynamicGroupsTotalCacheItem();
        if ($cacheItem && $cacheItem->isHit()) {
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return (int)$cacheItem->get();
        }

        return 0;
    }

    public function isDynamicProductGroupTotalCached(): bool
    {
        $totalCacheItem = $this->getDynamicGroupsTotalCacheItem();

        return $totalCacheItem->isHit();
    }

    public function setDynamicProductGroupTotal(int $total): void
    {
        $totalCacheItem = $this->getDynamicGroupsTotalCacheItem();
        $this->setTotalInCache($totalCacheItem, $total);
    }

    public function setDynamicProductGroupOffset($products, int $offset): void
    {
        $cacheItem = $this->getDynamicProductGroupOffsetCacheItem($offset);
        $cacheItem->set($products);
        $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
        $this->cache->save($cacheItem);

        // For each pagination, we will try to set the cache if we have reached the last page.
        $this->cacheHandler->dynamicProductGroupWarmUp($this->start, $this->count);
    }

    public function isCacheWarmedUp(int $offset): bool
    {
        if ($offset > 0) {
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

    public function getCachedCategoryIds(int $offset): array
    {
        $categories = [];
        $cacheItem = $this->getDynamicProductGroupOffsetCacheItem($offset);
        if ($cacheItem->isHit()) {
            $categories = (array)$cacheItem->get();
        }

        return $categories;
    }

    public function getDynamicProductGroupCachedTotal(): int
    {
        return $this->getDynamicProductGroupTotalFromCache();
    }
}
