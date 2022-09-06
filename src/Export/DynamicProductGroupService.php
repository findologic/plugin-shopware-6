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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

use function serialize;
use function unserialize;

class DynamicProductGroupService
{
    public const CONTAINER_ID = 'fin_search.dynamic_product_group';
    private const CACHE_ID_PRODUCT_GROUP = 'fl_product_groups';
    private const CACHE_LIFETIME_PRODUCT_GROUP = 60 * 11;

    protected ProductStreamBuilderInterface $productStreamBuilder;

    protected EntityRepositoryInterface $productRepository;

    protected Context $context;

    protected CacheItemPoolInterface $cache;

    protected string $shopkey;

    protected int $start;

    private SalesChannelEntity $salesChannel;

    private EntityRepositoryInterface $categoryRepository;

    private function __construct(
        EntityRepository $productRepository,
        EntityRepository $categoryRepository,
        ProductStreamBuilder $productStreamBuilder,
        CacheItemPoolInterface $cache,
        Context $context,
        string $shopkey,
        int $start
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->cache = $cache;
        $this->context = $context;
        $this->shopkey = $shopkey;
        $this->start = $start;
    }

    public static function getInstance(
        ContainerInterface $container,
        EntityRepository $productRepository,
        EntityRepository $categoryRepository,
        CacheItemPoolInterface $cache,
        Context $context,
        string $shopkey,
        int $start
    ): DynamicProductGroupService {
        if ($container->has(self::CONTAINER_ID)) {
            $dynamicProductGroupService = $container->get(self::CONTAINER_ID);
        } else {
            $dynamicProductGroupService = new DynamicProductGroupService(
                $productRepository,
                $categoryRepository,
                $container->get(ProductStreamBuilder::class),
                $cache,
                $context,
                $shopkey,
                $start
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
        $cacheItem = $this->getCacheItem();
        $products = $this->parseProductGroups();
        if (Utils::isEmpty($products)) {
            return;
        }
        $cacheItem->set(serialize($products));
        $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
        $this->cache->save($cacheItem);
    }

    public function isWarmedUp(): bool
    {
        if ($this->start === 0) {
            return false;
        }

        $cacheItem = $this->getCacheItem();
        if ($cacheItem && $cacheItem->isHit()) {
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);

            return true;
        }

        return false;
    }

    private function parseProductGroups(): array
    {
        $criteria = $this->buildCriteria();

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $this->context)->getEntities();

        if ($categories === null || empty($categories->getElements())) {
            return [];
        }

        $products = [];
        foreach ($categories->getElements() as $categoryEntity) {
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
            $productIds = $this->productRepository->searchIds($criteria, $this->context)->getIds();
            foreach ($productIds as $productId) {
                $products[$productId][$categoryEntity->getId()] = $categoryEntity;
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
        $cacheItem = $this->getCacheItem();
        if ($cacheItem->isHit()) {
            $categories = unserialize($cacheItem->get());
        }
        if (!Utils::isEmpty($categories) && isset($categories[$productId])) {
            return $categories[$productId];
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

    private function getCacheItem(): CacheItemInterface
    {
        $id = sprintf('%s_%s', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey);

        return $this->cache->getItem($id);
    }
}
