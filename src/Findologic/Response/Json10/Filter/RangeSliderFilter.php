<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter;

class RangeSliderFilter extends Filter
{
    private string $minKey;

    private string $maxKey;

    private ?float $min = null;

    private ?float $max = null;

    private ?float $step = null;

    private string $unit = '';

    private array $totalRange = [];

    private array $selectedRange = [];

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

    public function setMin(float $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function getMin(): ?float
    {
        return $this->min;
    }

    public function setMax(float $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function getMax(): ?float
    {
        return $this->max;
    }

    public function getStep(): ?float
    {
        return $this->step;
    }

    public function setStep(?float $step): void
    {
        $this->step = $step;
    }

    public function setTotalRange(array $totalRange): void
    {
        $this->totalRange = $totalRange;
    }

    public function setSelectedRange(array $selectedRange): void
    {
        $this->selectedRange = $selectedRange;
    }

    public function getTotalRange(): array
    {
        return $this->totalRange;
    }

    public function getSelectedRange(): array
    {
        return $this->selectedRange;
    }
}
