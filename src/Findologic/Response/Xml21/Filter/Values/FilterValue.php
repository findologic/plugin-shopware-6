<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values;

use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\TranslatedName;
use Shopware\Core\Framework\Struct\Struct;

class FilterValue extends Struct
{
    public const DELIMITER = '>';

    protected ?string $uuid;

    private string $id;

    private string $name;

    private TranslatedName $translated;

    /**
     * @param string|null $filterName
     * This can be null because we do not want to set this for all filter values.
     * For e.g the category filter does not need to have a unique ID as its value is already unique.
     * The uuid is generated only for the values in which we need a unique ID for selection in storefront
     */
    public function __construct(string $id, string $name, ?string $filterName = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->translated = new TranslatedName($name);
        if ($filterName !== null) {
            $this->uuid = sprintf('%s%s%s', $filterName, self::DELIMITER, $id);
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

    public function getUuid(): ?string
    {
        return $this->uuid;
    }
}
