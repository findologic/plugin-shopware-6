<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Promotion extends Struct
{
    /** @var string */
    private $image;

    /** @var string */
    private $link;

    public function __construct(string $image, string $link)
    {
        $this->image = $image;
        $this->link = $link;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getLink(): string
    {
        return $this->link;
    }
}
