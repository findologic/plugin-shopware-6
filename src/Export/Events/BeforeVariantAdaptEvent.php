<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Events;

use FINDOLOGIC\Export\Data\Item;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Contracts\EventDispatcher\Event;

class BeforeVariantAdaptEvent extends Event
{
    public const NAME = 'fin_search.export.before_variant_adapt';

    /** @var ProductEntity */
    protected $product;

    /** @var Item */
    protected $item;

    public function __construct(ProductEntity $product, Item $item)
    {
        $this->product = $product;
        $this->item = $item;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    public function getItem(): Item
    {
        return $this->item;
    }
}
