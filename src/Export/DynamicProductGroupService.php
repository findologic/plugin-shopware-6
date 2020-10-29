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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
     * @var SalesChannelEntity
     */
    private $salesChannel;

    private $products;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    private function __construct(
        ContainerInterface $container,
        CacheItemPoolInterface $cache,
        Context $context,
        string $shopkey,
        int $start
    ) {
        $this->container = $container;
        $this->cache = $cache;
        $this->shopkey = $shopkey;
        $this->start = $start;
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
        int $start
    ): DynamicProductGroupService {
        if ($container->has(self::CONTAINER_ID)) {
            $dynamicProductGroupService = $container->get(self::CONTAINER_ID);
        } else {
            $dynamicProductGroupService = new DynamicProductGroupService(
                $container,
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

    public function warmUp()
    {
        $id = $this->getCacheId();
        $cacheItem = $this->cache->getItem($id);
        $mainCategoryId = $this->salesChannel->getNavigationCategoryId();
        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(new Criteria([$mainCategoryId]), $this->context)->first();
        $categories = $category->getChildren();
        $this->parseProductGroups($categories);
        if (!Utils::isEmpty($this->products)) {
            $cacheItem->set($this->products);
            $cacheItem->expiresAfter(self::CACHE_LIFETIME_PRODUCT_GROUP);
            $this->cache->save($cacheItem);
        }
    }

    public function isWarmedUp()
    {
        $id = $this->getCacheId();
        $cacheItem = $this->cache->getItem($id);

        return $cacheItem->isHit() && $this->start > 0;
    }

    private function parseProductGroups(?CategoryCollection $categories): void
    {
        if ($categories) {
            $products = [];
            foreach ($categories->getElements() as $categoryEntity) {
                $productStream = $categoryEntity->getCategory()->getProductStream();
                if ($productStream) {
                    $filters = $this->productStreamBuilder->buildFilters(
                        $productStream->getId(),
                        $this->context
                    );

                    $criteria = new Criteria();
                    $criteria->addFilter(...$filters);
                    $productIds = $this->productRepository->searchIds($criteria, $this->context)->getIds();
                    foreach ($productIds as $productId) {
                        $products[$productId['id']][] = $categoryEntity->getCategory();
                    }
                }
            }

            $this->products = $products;
        }
    }

    private function getCacheId()
    {
        return sprintf('%s_%s', self::CACHE_ID_PRODUCT_GROUP, $this->shopkey);
    }

    /**
     * @return CategoryEntity[]
     */
    public function getCategories(string $productId): array
    {
        if (!Utils::isEmpty($this->products) && isset($this->products[$productId])) {
            return $this->products[$productId];
        }

        return [];
    }
}
