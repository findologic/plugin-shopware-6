<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Item;
use Shopware\Core\Content\Product\ProductEntity;

interface ExportItemAdapterInterface
{
    public function adapt(Item $item, ProductEntity $product): ?Item;
}
