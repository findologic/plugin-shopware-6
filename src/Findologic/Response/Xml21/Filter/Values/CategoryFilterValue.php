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
    /**
     * @return bool
     */
    public function isSelected(): bool
    {
        return $this->selected;
    }

    /**
     * @return CategoryFilterValue
     */
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

    /**
     * @return CategoryFilterValue
     */
    public function addValue(CategoryFilterValue $filter): CategoryFilterValue
    {
        $this->values[] = $filter;

        return $this;
    }

    /**
     * @param int $frequency
     * @return CategoryFilterValue
     */
    public function setFrequency(int $frequency): CategoryFilterValue
    {
        $this->frequency = $frequency;
        return $this;
    }

    /**
     * @return int
     */
    public function getFrequency(): int
    {
        return $this->frequency;
    }
}
