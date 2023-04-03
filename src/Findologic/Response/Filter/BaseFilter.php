<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Filter;

use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\FilterValue;

abstract class BaseFilter
{
    public const RATING_FILTER_NAME = 'rating';
    public const CAT_FILTER_NAME = 'cat';

    protected ?string $displayType;

    protected string $id;

    protected string $name;

    /** @var FilterValue[] */
    protected array $values;

    protected bool $hidden = false;

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

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }
}
