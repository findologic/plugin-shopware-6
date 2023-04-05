<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Services;

use FINDOLOGIC\FinSearch\Export\Search\CategorySearcher;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Services\AbstractCatUrlBuilderService;
use Symfony\Component\Routing\RouterInterface;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryEntity;

class CatUrlBuilderService extends AbstractCatUrlBuilderService
{
    public function __construct(
        ExportContext $exportContext,
        CategorySearcher $categorySearcher,
        RouterInterface $router
    ) {
        parent::__construct($categorySearcher, $exportContext, $router);
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
}
