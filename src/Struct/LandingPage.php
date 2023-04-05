<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class LandingPage extends Struct
{
    public function __construct(
        protected readonly string $link
    ) {
    }

    public function getLink(): string
    {
        return $this->link;
    }
}
