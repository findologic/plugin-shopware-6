<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ReleaseDateSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        // Shopware currently does not support it so we return false here
        return false;
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchNavigationRequest $searchNavigationRequest): void
    {
        $searchNavigationRequest->setOrder('dateadded ' . $fieldSorting->getDirection());
    }
}
