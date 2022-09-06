<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Utils\Utils;
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

class DynamicProductGroupService
{
    public const CONTAINER_ID = 'fin_search.dynamic_product_group';

    protected ProductStreamBuilderInterface $productStreamBuilder;

    protected EntityRepositoryInterface $productRepository;

    protected Context $context;

    protected CacheItemPoolInterface $cache;

    protected string $shopkey;

    protected int $start;

    protected int $count;

    private SalesChannelEntity $salesChannel;

    protected DynamicProductGroupCacheHandler $cacheHandler;

    private EntityRepositoryInterface $categoryRepository;

    private function __construct(
        EntityRepository $productRepository,
        EntityRepository $categoryRepository,
        ProductStreamBuilder $productStreamBuilder,
        DynamicProductGroupCacheHandler $cacheHandler,
        Context $context,
        string $shopkey,
        int $start,
        int $count
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->context = $context;
        $this->shopkey = $shopkey;
        $this->start = $start;
        $this->count = $count;

        $this->cacheHandler = $cacheHandler;
        $this->cacheHandler->setShopkey($shopkey);
    }

    public static function getInstance(
        ContainerInterface $container,
        EntityRepository $productRepository,
        EntityRepository $categoryRepository,
        CacheItemPoolInterface $cache,
        Context $context,
        string $shopkey,
        int $start,
        int $count
    ): DynamicProductGroupService {
        if ($container->has(self::CONTAINER_ID)) {
            $dynamicProductGroupService = $container->get(self::CONTAINER_ID);
        } else {
            $cacheHandler = new DynamicProductGroupCacheHandler($cache);
            $dynamicProductGroupService = new DynamicProductGroupService(
                $productRepository,
                $categoryRepository,
                $container->get(ProductStreamBuilder::class),
                $cacheHandler,
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
            $this->cacheHandler->warmUpDynamicProductGroups($this->start, $this->count);

            return;
        }

        $this->cacheDynamicProductGroupsTotal();
        $this->cacheDynamicProductOffset($products);

        $this->cacheHandler->warmUpDynamicProductGroups($this->start, $this->count);
    }

    public function isCurrentOffsetWarmedUp(): bool
    {
        return $this->cacheHandler->isCacheWarmedUp($this->start);
    }

    public function areDynamicProductGroupsCached(): bool
    {
        return $this->cacheHandler->areDynamicProductGroupsCached();
    }

    public function getDynamicProductGroupsTotal(): int
    {
        return $this->cacheHandler->getDynamicProductGroupsCachedTotal();
    }

    /**
     * @return CategoryEntity[]
     */
    public function getCategories(string $productId): array
    {
        $categories = $this->cacheHandler->getCachedCategoryIdsForCurrentOffset($this->start);
        if (!Utils::isEmpty($categories) && isset($categories[$productId])) {
            $categoryIds = $categories[$productId];
            $criteria = $this->buildCriteria();
            $criteria->setIds($categoryIds);

            return $this->categoryRepository->search($criteria, $this->context)->getElements();
        }

        return [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function parseProductGroups(): array
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
                $products[$productId][$categoryEntity->getId()] = $categoryEntity->getId();
            }
        }

        return $products;
    }

    protected function buildCriteria(): Criteria
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

    protected function getTotalDynamicProductGroupsCount(): int
    {
        $total = 0;
        $criteria = $this->buildCriteria();
        $categories = $this->getCategoriesFromCriteria($criteria);
        foreach ($categories as $categoryEntity) {
            $productStream = $categoryEntity->getProductStream();
            if (!$productStream) {
                continue;
            }

            $total++;
        }

        return $total;
    }

    protected function getCategoriesFromCriteria(Criteria $criteria): ?CategoryCollection
    {
        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $this->context)->getEntities();

        if ($categories === null || $categories->count() === 0) {
            return null;
        }

        return $categories;
    }

    /**
     * Sets the dynamic product groups total count in cache if it is not already set. This is important
     * as otherwise we wouldn't know when we're done fetching all dynamic product groups during the export.
     */
    protected function cacheDynamicProductGroupsTotal(): void
    {
        $isTotalCached = $this->cacheHandler->isDynamicProductGroupTotalCached();
        if (!$isTotalCached || $this->start === 0) {
            $total = $this->getTotalDynamicProductGroupsCount();
            $this->cacheHandler->setDynamicProductGroupTotal($total);
        }
    }

    /**
     * Sets the dynamic product groups in cache for each pagination. This is required so that each
     * subsequent export request fetches the correct dynamic product groups for that offset.
     */
    protected function cacheDynamicProductOffset(array $products): void
    {
        $this->cacheHandler->setDynamicProductGroupsOffset($products, $this->start);
    }
}
