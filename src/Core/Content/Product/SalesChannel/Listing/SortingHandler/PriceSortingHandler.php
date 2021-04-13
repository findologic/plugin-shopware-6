<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class PriceSortingHandler implements SortingHandlerInterface
{
    private const FIELDS = [
        'product.cheapestPrice', // Shopware >= 6.4
        'product.listingPrices', // Shopware < 6.4
    ];

    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return in_array($fieldSorting->getField(), self::FIELDS);
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchNavigationRequest $searchNavigationRequest): void
    {
        $searchNavigationRequest->setOrder('price ' . $fieldSorting->getDirection());
    }
}
