<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter;

use Shopware\Core\Framework\Struct\Struct;

class Media extends Struct
{
    public function __construct(
        private readonly ?string $url
    ) {
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
