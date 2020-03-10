<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter;

use Shopware\Core\Framework\Struct\Struct;

class Media extends Struct
{
    /** @var string|null */
    private $url;

    public function __construct(?string $url)
    {
        $this->url = $url;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }
}
