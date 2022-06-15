<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Item;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;

interface ExportItemAdapterInterface
{
    public function adapt(Item $item, ProductEntity $product, ?LoggerInterface $logger = null): ?Item;

    public function adaptVariant(Item $item, ProductEntity $product): ?Item;
}
