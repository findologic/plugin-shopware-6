<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\PriceSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ProductNameSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ReleaseDateSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ScoreSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\SortingHandlerInterface;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\TopSellerSortingHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class SortingHandlerService
{
    public function handle(SearchNavigationRequest $searchNavigationRequest, Criteria $criteria): void
    {
        foreach ($this->getSortingHandlers() as $handler) {
            foreach ($criteria->getSorting() as $fieldSorting) {
                if ($handler->supportsSorting($fieldSorting)) {
                    $handler->generateSorting($fieldSorting, $searchNavigationRequest);
                }
            }
        }
    }

    /**
     * @return SortingHandlerInterface[]
     */
    protected function getSortingHandlers(): array
    {
        return [
            new ScoreSortingHandler(),
            new PriceSortingHandler(),
            new ProductNameSortingHandler(),
            new ReleaseDateSortingHandler(),
            new TopSellerSortingHandler(),
        ];
    }
}
