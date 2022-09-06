<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\FinSearch\Validators\DynamicProductGroupsConfiguration;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use FINDOLOGIC\FinSearch\Validators\ExportConfigurationBase;
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

    protected EntityRepositoryInterface $productRepository;

    private EntityRepositoryInterface $categoryRepository;

    protected ProductStreamBuilderInterface $productStreamBuilder;

    protected DynamicProductGroupCacheHandler $cacheHandler;

    protected Context $context;

    protected ExportConfigurationBase $exportConfig;

    private SalesChannelEntity $salesChannel;

    private function __construct(
        EntityRepository $productRepository,
        EntityRepository $categoryRepository,
        ProductStreamBuilder $productStreamBuilder,
        DynamicProductGroupCacheHandler $cacheHandler,
        Context $context,
        ExportConfigurationBase $exportConfig
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->context = $context;
        $this->exportConfig = $exportConfig;

        $this->cacheHandler = $cacheHandler;
        $this->cacheHandler->setShopkey($exportConfig->getShopkey());
    }

    public static function getInstance(
        ContainerInterface $container,
        EntityRepository $productRepository,
        EntityRepository $categoryRepository,
        CacheItemPoolInterface $cache,
        Context $context,
        ExportConfigurationBase $exportConfig
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
                $exportConfig
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
        $this->cacheDynamicProductGroupsTotal();

        $products = $this->parseProductGroups();
        if ($this->isLastPage()) {
            $this->cacheHandler->setWarmedUpCacheItem();
        }

        $this->cacheDynamicProductOffset($products);
    }

    public function areDynamicProductGroupsCached(): bool
    {
        return $this->cacheHandler->areDynamicProductGroupsCached();
    }

    public function getDynamicProductGroupsTotal(): int
    {
        return $this->cacheHandler->getDynamicProductGroupsCachedTotal();
    }

    public function clearGeneralCache(): void
    {
        $this->cacheHandler->clearGeneralCache();
    }

    public function getCategories(string $productId): CategoryCollection
    {
        $start = 0;
        $categoryCollection = new CategoryCollection();

        while ($this->cacheHandler->isOffsetCacheWarmedUp($start)) {
            $categories = $this->cacheHandler->getCachedCategoriesForCurrentOffset($start);

            if (!Utils::isEmpty($categories) && isset($categories[$productId])) {
                $categoryIds = $categories[$productId];
                $criteria = $this->buildCriteria();
                $criteria->setIds($categoryIds);

                $categoryCollection->merge(
                    $this->categoryRepository->search($criteria, $this->context)->getEntities()
                );
            }

            $start += DynamicProductGroupsConfiguration::DEFAULT_COUNT_PARAM;
        }

        return $categoryCollection;
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function parseProductGroups(): array
    {
        $criteria = $this->buildCriteria();
        $criteria->setOffset($this->exportConfig->getStart())
            ->setLimit($this->exportConfig->getCount());

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $this->context)
            ->getEntities();

        if (!$categories->count()) {
            return [];
        }

        $products = [];
        foreach ($categories as $categoryEntity) {
            if (!$productStream = $categoryEntity->getProductStream()) {
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

    /**
     * Sets the dynamic product groups total count in cache if it is not already set. This is important
     * as otherwise we wouldn't know when we're done fetching all dynamic product groups during the export.
     */
    protected function cacheDynamicProductGroupsTotal(): void
    {
        if (
            !$this->cacheHandler->isDynamicProductGroupTotalCached() ||
            $this->exportConfig->getStart() === 0
        ) {
            $total = $this->getTotalDynamicProductGroupsCount();
            $this->cacheHandler->setDynamicProductGroupTotal($total);
        }
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

    /**
     * Sets the dynamic product groups in cache for each pagination. This is required so that each
     * subsequent export request fetches the correct dynamic product groups for that offset.
     */
    protected function cacheDynamicProductOffset(array $products): void
    {
        $this->cacheHandler->setDynamicProductGroupsOffset($products, $this->exportConfig->getStart());
    }

    protected function getCategoriesFromCriteria(Criteria $criteria): ?CategoryCollection
    {
        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $this->context)->getEntities();

        return $categories->count() ? $categories : null;
    }

    protected function isLastPage(): bool
    {
        $currentTotal = $this->exportConfig->getStart() + $this->exportConfig->getCount();
        return $currentTotal >= $this->getDynamicProductGroupsTotal();
    }
}
