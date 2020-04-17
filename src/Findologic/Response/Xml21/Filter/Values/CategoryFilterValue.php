<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values;

class CategoryFilterValue extends FilterValue
{
    /** @var bool */
    private $selected = false;

    /** @var CategoryFilterValue[] */
    private $values;

    /**
     * @return bool
     */
    public function isSelected(): bool
    {
        return $this->selected;
    }

    /**
     * @param bool $selected
     */
    public function setSelected(bool $selected): void
    {
        $this->selected = $selected;
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
     * @param CategoryFilterValue $filter
     */
    public function addValue(CategoryFilterValue $filter): void
    {
        $this->values[] = $filter;
    }
}
