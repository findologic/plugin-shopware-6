<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Summary;
use Shopware\Core\Content\Product\ProductEntity;

class SummaryAdapter
{
    public function adapt(ProductEntity $product): ?Summary
    {
        return null;
    }
}
