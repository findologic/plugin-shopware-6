<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Bonus;
use Shopware\Core\Content\Product\ProductEntity;

class BonusAdapter
{
    public function adapt(ProductEntity $product): ?Bonus
    {
        return null;
    }
}
