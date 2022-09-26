<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Services;

use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Services\AbstractCatUrlBuilderService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryCollection;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryEntity;

class CatUrlBuilderService extends AbstractCatUrlBuilderService
{
    private SalesChannelContext $salesChannelContext;

    private EntityRepository $categoryRepository;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        EntityRepository $categoryRepository,
        ExportContext $exportContext,
        RouterInterface $router
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->categoryRepository = $categoryRepository;

        parent::__construct($exportContext, $router);
    }

    /** @inheritDoc */
    protected function buildCategoryUrls(CategoryEntity $category): array
    {
        $categoryUrls = $this->buildCategorySeoUrl($category);

        $categoryUrls[] = sprintf(
            '/%s',
            ltrim(
                $this->router->generate(
                    'frontend.navigation.page',
                    ['navigationId' => $category->id],
                    RouterInterface::ABSOLUTE_PATH
                ),
                '/'
            )
        );

        return $categoryUrls;
    }

    protected function fetchParentsFromCategoryPath(string $categoryPath): CategoryCollection
    {
        $parentIds = array_filter(explode('|', $categoryPath));
        $criteria = new Criteria($parentIds);
        $criteria->addAssociation('seoUrls');

        $categories = $this->categoryRepository
            ->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities();

        /** @var CategoryCollection $sdkCategories */
        $sdkCategories = Utils::createSdkCollection(
            CategoryCollection::class,
            CategoryEntity::class,
            $categories
        );

        return $sdkCategories;
    }
}
