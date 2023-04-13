<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\Shopware6Common\Export\Search\AbstractProductStreamSearcher;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductStreamSearcher extends AbstractProductStreamSearcher
{
    private const CACHE_KEY = 'finsearch_stream_filters';

    public function __construct(
        protected readonly ProductStreamBuilder $productStreamBuilder,
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly SalesChannelRepository $salesChannelProductRepository,
        protected readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function isProductInDynamicProductGroup(string $productId, string $streamId): bool
    {
        if (!$filters = $this->getFromCache($streamId)) {
            $filters = $this->productStreamBuilder->buildFilters($streamId, $this->salesChannelContext->getContext());

            $this->saveToCache($filters, $streamId);
        }

        $criteria = new Criteria([$productId]);
        $criteria->addFilter(...$filters);

        return !!$this->salesChannelProductRepository->searchIds($criteria, $this->salesChannelContext)->firstId();
    }

    protected function getFromCache(string $streamId): ?array
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY . '_' . $streamId);
        if ($cacheItem->isHit()) {
            return unserialize($cacheItem->get());
        }

        return null;
    }

    protected function saveToCache(array $filters, string $streamId): void
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY . '_' . $streamId)
            ->set(serialize($filters));
        $this->cache->save($cacheItem);
    }
}
