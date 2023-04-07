<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Filter;

use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\FilterValue;

abstract class BaseFilter
{
    public const RATING_FILTER_NAME = 'rating';
    public const CAT_FILTER_NAME = 'cat';
    public const VENDOR_FILTER_NAME = 'vendor';

    protected ?string $displayType;

    protected bool $hidden = false;

    /**
     * @param FilterValue[] $values
     */
    public function __construct(
        protected readonly string $id,
        protected readonly string $name,
        protected array $values = [],
    ) {
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

    /**
     * @return FilterValue[]
     */
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
