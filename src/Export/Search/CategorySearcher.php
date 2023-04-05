<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Search\AbstractCategorySearcher;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryCollection as SdkCategoryCollection;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryEntity;

class CategorySearcher extends AbstractCategorySearcher
{
    public function __construct(
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly EntityRepository $categoryRepository,
        ExportContext $exportContext
    ) {
        parent::__construct($exportContext);
    }

    public function fetchParentsFromCategoryPath(string $categoryPath): SdkCategoryCollection
    {
        $parentIds = array_filter(explode('|', $categoryPath));
        $criteria = new Criteria($parentIds);
        $criteria->addAssociation('seoUrls');

        $categories = $this->categoryRepository
            ->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities();

        /** @var SdkCategoryCollection $sdkCategories */
        $sdkCategories = Utils::createSdkCollection(
            SdkCategoryCollection::class,
            CategoryEntity::class,
            $categories
        );

        return $sdkCategories;
    }

    public function getProductStreamCategories(?int $count = null, ?int $offset = null): SdkCategoryCollection
    {
        $categories = $this->categoryRepository
            ->search($this->buildCategoryCriteria($count, $offset), $this->salesChannelContext->getContext())
            ->getEntities()
            ->getElements();

        /** @var SdkCategoryCollection $sdkCategories */
        $sdkCategories = Utils::createSdkCollection(
            SdkCategoryCollection::class,
            CategoryEntity::class,
            new CategoryCollection($categories),
        );

        return $sdkCategories;
    }

    protected function buildCategoryCriteria(?int $count = null, ?int $offset = null): Criteria
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

        if ($count) {
            $criteria->setLimit($count);
        }
        if ($offset) {
            $criteria->setOffset($offset);
        }

        return $criteria;
    }
}
