<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Filter;

abstract class BaseFilter
{
    /** @var string|null */
    protected $displayType;

    /** @var string */
    protected $id;

    /** @var string */
    protected $name;

    /** @var array */
    protected $values;

    /**
     * @param string $id
     * @param string $name
     * @param array $values
     */
    public function __construct(string $id, string $name, array $values = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->values = $values;
    }

    public function getDisplayType(): ?string
    {
        return $this->displayType;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValues(): array
    {
        return $this->values;
    }
}
