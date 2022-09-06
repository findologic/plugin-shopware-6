<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Events;

use FINDOLOGIC\Export\Data\Item;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Contracts\EventDispatcher\Event;

class AfterItemBuildEvent extends Event
{
    public const NAME = 'fin_search.export.after_item_builb';

    protected Item $item;

    public function __construct(Item $item)
    {
        $this->item = $item;
    }

    public function getItem(): Item
    {
        return $this->item;
    }
}
