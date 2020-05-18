<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class LandingPage extends Struct
{
    /** @var string */
    protected $link;

    public function __construct(string $link)
    {
        $this->link = $link;
    }

    public function getLink(): string
    {
        return $this->link;
    }
}
