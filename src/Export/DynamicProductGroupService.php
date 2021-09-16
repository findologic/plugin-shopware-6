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

    /** @var CacheHandler */
    protected $cacheHandler;

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
        CacheHandler $cacheHandler,
        Context $context,
        string $shopkey,
        int $start,
        int $count
    ) {
        $this->container = $container;
        $this->shopkey = $shopkey;
        $this->start = $start;
        $this->count = $count;
        $this->productStreamBuilder = $container->get(ProductStreamBuilder::class);
        $this->productRepository = $container->get('product.repository');
        $this->categoryRepository = $container->get('category.repository');
        $this->context = $context;
        $this->cacheHandler = $cacheHandler;
        $this->cacheHandler->setShopkey($shopkey);
    }

    public static function getInstance(
        ContainerInterface $container,
        CacheHandler $cacheHandler,
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
            $this->cacheHandler->dynamicProductGroupWarmUp($this->start, $this->count);

            return;
        }

        $this->cacheDynamicProductGroupTotal();
        $this->cacheDynamicProductOffset($products);

        $this->cacheHandler->dynamicProductGroupWarmUp($this->start, $this->count);
    }

    public function isOffsetWarmedUp(): bool
    {
        return $this->cacheHandler->isCacheWarmedUp($this->start);
    }

    public function isDynamicProductGroupsCached(): bool
    {
        return $this->cacheHandler->isDynamicProductGroupsCached();
    }

    public function getDynamicProductGroupTotal()
    {
        return $this->cacheHandler->getDynamicProductGroupCachedTotal();
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
        $categories = $this->cacheHandler->getCachedCategoryIds($this->start);
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

    /**
     * Gets the total count of available dynamic product groups to be exported.
     */
    private function getTotalDynamicProductGroupsCount(): int
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

    private function getCategoriesFromCriteria(Criteria $criteria): ?CategoryCollection
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
    private function cacheDynamicProductGroupTotal(): void
    {
        $isTotalCached = $this->cacheHandler->isDynamicProductGroupTotalCached();
        if (!$isTotalCached) {
            $total = $this->getTotalDynamicProductGroupsCount();
            $this->cacheHandler->setDynamicProductGroupTotal($total);
        }
    }

    /**
     * Sets the dynamic product groups in cache for each pagination. This is required so that each
     * subsequent export request fetches the correct dynamic product groups for that offset.
     */
    private function cacheDynamicProductOffset($products): void
    {
        $this->cacheHandler->setDynamicProductGroupOffset($products, $this->start);
    }
}
