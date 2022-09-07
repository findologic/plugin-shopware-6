<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class DynamicProductGroupCacheHandler
{
    private const CACHE_ID_PRODUCT_GROUP = 'fl_product_groups';
    private const CACHE_LIFETIME_PRODUCT_GROUP = 60 * 11;

    protected CacheItemPoolInterface $cache;

    protected ?string $shopkey;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function setShopkey(string $shopkey): void
    {
        $this->shopkey = $shopkey;
    }

    public function setWarmedUpCacheItem(): void
    {
        $cacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();
        $cacheItem->set(true);
        $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
        $this->cache->save($cacheItem);
    }

    public function isDynamicProductGroupTotalCached(): bool
    {
        $totalCacheItem = $this->getDynamicGroupsTotalCacheItem();

        return $totalCacheItem->isHit();
    }

    public function isOffsetCacheWarmedUp(int $offset): bool
    {
        $cacheItem = $this->getDynamicProductGroupOffsetCacheItem($offset);
        if ($cacheItem->isHit()) {
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return true;
        }

        return false;
    }

    public function areDynamicProductGroupsCached(): bool
    {
        $cacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();
        if ($cacheItem->isHit()) {
            $cacheItem->set(true);
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return true;
        }

        return false;
    }

    public function getCachedCategoriesForCurrentOffset(int $offset): array
    {
        $categories = [];
        $cacheItem = $this->getDynamicProductGroupOffsetCacheItem($offset);
        if ($cacheItem->isHit()) {
            $categories = (array)$cacheItem->get();
        }

        return $categories;
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
        $cacheItem = $this->getDynamicProductGroupOffsetCacheItem($offset);
        $cacheItem->set($products);
        $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
        $this->cache->save($cacheItem);
    }

    public function clearGeneralCache(): void
    {
        $totalCacheItem = $this->getDynamicGroupsTotalCacheItem();
        $warmedUpCacheItem = $this->getDynamicProductGroupWarmedUpCacheItem();

        $this->cache->deleteItems([
            $totalCacheItem->getKey(),
            $warmedUpCacheItem->getKey()
        ]);
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

    protected function getDynamicGroupsTotalCacheItem(): CacheItemInterface
    {
        $id = sprintf('%s_%s_total', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey);

        return $this->cache->getItem($id);
    }

    protected function getDynamicProductGroupTotalFromCache(): int
    {
        $cacheItem = $this->getDynamicGroupsTotalCacheItem();
        if ($cacheItem->isHit()) {
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return $cacheItem->get();
        }

        return 0;
    }

    protected function setTotalInCache(CacheItemInterface $cacheItem, int $total): void
    {
        $cacheItem->set($total);
        $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
        $this->cache->save($cacheItem);
    }
}
