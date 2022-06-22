<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Sort;
use Shopware\Core\Content\Product\ProductEntity;

class SortAdapter
{
    public function adapt(ProductEntity $product): ?Sort
    {
        return null;
    }
}
