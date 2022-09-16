<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\FinSearch\Validators\DynamicProductGroupsConfiguration;
use FINDOLOGIC\FinSearch\Validators\ExportConfigurationBase;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Services\AbstractDynamicProductGroupService;
use Psr\Cache\CacheItemPoolInterface;
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
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DynamicProductGroupService extends AbstractDynamicProductGroupService
{
    protected EntityRepositoryInterface $productRepository;

    protected EntityRepositoryInterface $categoryRepository;

    protected ProductStreamBuilderInterface $productStreamBuilder;

    protected Context $context;

    protected SalesChannelContext $salesChannelContext;

    protected ExportConfigurationBase $exportConfig;

    protected ExportContext $exportContext;

    public function __construct(
        EntityRepository $productRepository,
        EntityRepository $categoryRepository,
        ProductStreamBuilder $productStreamBuilder,
        SalesChannelContext $salesChannelContext,
        ExportConfigurationBase $exportConfig,
        CacheItemPoolInterface $cache,
        ExportContext $exportContext
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->context = $salesChannelContext->getContext();
        $this->salesChannelContext = $salesChannelContext;
        $this->exportConfig = $exportConfig;

        parent::__construct($cache, $exportContext);
    }

    public function getCategories(string $productId): array
    {
        $start = 0;
        $categoryCollection = new CategoryCollection();

        while ($this->cacheHandler->isOffsetCacheWarmedUp($start)) {
            $categories = $this->cacheHandler->getCachedCategoriesForCurrentOffset($start);

            if (!Utils::isEmpty($categories) && isset($categories[$productId])) {
                $categoryIds = $categories[$productId];
                $criteria = $this->buildCategoryCriteria();
                $criteria->setIds($categoryIds);

                $categoryCollection->merge(
                    $this->categoryRepository->search($criteria, $this->context)->getEntities()
                );
            }

            $start += DynamicProductGroupsConfiguration::DEFAULT_COUNT_PARAM;
        }

        return $categoryCollection->getElements();
    }

    protected function setMainNavigationCategoryId(): void
    {
        $this->mainCategoryId = $this->salesChannelContext->getSalesChannel()->getNavigationCategoryId();
    }

    protected function getShopkey(): string
    {
        return $this->exportConfig->getShopkey();
    }

    /**
     * @inheritDoc
     */
    protected function parseProductGroups(): array
    {
        /** @var CategoryCollection $categories */
        $categories = $this->getProductStreamCategories(true);

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

    protected function getProductStreamCategories(bool $paginated = false): array
    {
        return $this->categoryRepository
            ->search($this->buildCategoryCriteria($paginated), $this->context)
            ->getEntities()
            ->getElements();
    }

    protected function buildCategoryCriteria(bool $paginated = false): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('path', $this->exportContext->getNavigationCategoryId()));
        $criteria->addAssociation('seoUrls');
        $criteria->addAssociation('productStream');
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('productStreamId', null)]
            )
        );

        if ($paginated) {
            $criteria->setLimit($this->exportConfig->getCount());
            $criteria->setOffset($this->exportConfig->getStart());
        }

        return $criteria;
    }

    /**
     * @param CategoryEntity $categoryEntity
     */
    protected function hasProductStream($categoryEntity): bool
    {
        return !!$categoryEntity->getProductStream();
    }

    protected function isFirstPage(): bool
    {
        return $this->exportConfig->getStart() === 0;
    }

    protected function isLastPage(): bool
    {
        $currentTotal = $this->exportConfig->getStart() + $this->exportConfig->getCount();

        return $currentTotal >= $this->getDynamicProductGroupsTotal();
    }

    protected function getCurrentOffset(): int
    {
        return $this->exportConfig->getStart();
    }
}
