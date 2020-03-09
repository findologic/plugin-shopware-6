<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter;

class RangeSliderFilter extends Filter
{
    /** @var string */
    private $minKey;

    /** @var string */
    private $maxKey;

    /** @var string */
    private $unit = '€';

    public function __construct(string $id, string $name, array $values = [])
    {
        parent::__construct($id, $name, $values);
        $this->minKey = sprintf('min-%s', $id);
        $this->maxKey = sprintf('max-%s', $id);
    }

    public function getMinKey(): string
    {
        return $this->minKey;
    }

    public function getMaxKey(): string
    {
        return $this->maxKey;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }
}
