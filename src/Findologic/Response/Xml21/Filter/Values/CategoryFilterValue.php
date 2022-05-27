<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values;

class CategoryFilterValue extends FilterValue
{
    /** @var bool */
    private $selected = false;

    /** @var CategoryFilterValue[] */
    private $values;

    /** @var int */
    private $frequency = 0;

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function setSelected(bool $selected): CategoryFilterValue
    {
        $this->selected = $selected;

        return $this;
    }

    /**
     * @return CategoryFilterValue[]
     */
    public function getValues(): array
    {
        if (empty($this->values)) {
            return [];
        }

        return $this->values;
    }

    public function addValue(CategoryFilterValue $filter): CategoryFilterValue
    {
        $this->values[] = $filter;

        return $this;
    }

    public function setFrequency(?int $frequency): CategoryFilterValue
    {
        if ($frequency === null) {
            $frequency = 0;
        }

        $this->frequency = $frequency;

        return $this;
    }

    public function getFrequency(): int
    {
        return $this->frequency;
    }
}
