<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

interface SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool;

    public function generateSorting(
        FieldSorting $fieldSorting,
        SearchNavigationRequest $searchNavigationRequest
    ): void;
}
