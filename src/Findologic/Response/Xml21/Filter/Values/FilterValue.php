<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values;

use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\TranslatedName;
use Shopware\Core\Framework\Struct\Struct;

class FilterValue extends Struct
{
    /** @var string */
    protected $uuid;

    /** @var string */
    private $id;

    /** @var string */
    private $name;

    /** @var TranslatedName */
    private $translated;

    public function __construct(string $id, string $name, ?string $filterName = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->translated = new TranslatedName($name);
        if ($filterName) {
            $this->uuid = sprintf('%s-%s', $filterName, $id);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTranslated(): TranslatedName
    {
        return $this->translated;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
