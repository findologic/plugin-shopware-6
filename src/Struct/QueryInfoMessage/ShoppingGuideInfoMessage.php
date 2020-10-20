<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\QueryInfoMessage;

class ShoppingGuideInfoMessage extends QueryInfoMessage
{
    /** @var string */
    protected $shoppingGuide;

    public function __construct(string $shoppingGuide)
    {
        $this->shoppingGuide = $shoppingGuide;
    }

    public function getShoppingGuide(): string
    {
        return $this->shoppingGuide;
    }
}
