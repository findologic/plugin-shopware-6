<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ScoreSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return ($fieldSorting->getField() === '_score');
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchNavigationRequest $searchNavigationRequest): void
    {
        // Here we do not do anything, because FINDOLOGIC automatically orders by relevance.
    }
}
