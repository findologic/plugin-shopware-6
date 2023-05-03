<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\QueryInfoMessage;

class ShoppingGuideInfoMessage extends QueryInfoMessage
{
    public function __construct(
        protected readonly string $shoppingGuide
    ) {
    }

    public function getShoppingGuide(): string
    {
        return $this->shoppingGuide;
    }
}
