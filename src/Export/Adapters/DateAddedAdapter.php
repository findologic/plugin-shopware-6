<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\DateAdded;
use Shopware\Core\Content\Product\ProductEntity;

class DateAddedAdapter
{
    public function adapt(ProductEntity $product): ?DateAdded
    {
        $releaseDate = $product->getReleaseDate();

        if (!$releaseDate) {
            return null;
        }

        $dateAdded = new DateAdded();
        $dateAdded->setDateValue($releaseDate);

        return $dateAdded;
    }
}
