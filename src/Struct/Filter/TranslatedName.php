<?php

namespace FINDOLOGIC\FinSearch\Struct\Filter;

use Shopware\Core\Framework\Struct\Struct;

class TranslatedName extends Struct
{
    /** @var string */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
