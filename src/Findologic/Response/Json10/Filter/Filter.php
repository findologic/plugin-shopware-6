<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter;

use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\ColorFilter as ApiColorFilter;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\Filter as ApiFilter;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\ImageFilter as ApiImageFilter;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\LabelFilter as ApiLabelFilter;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\SelectFilter as ApiSelectFilter;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\RangeSliderFilter as ApiRangeSliderFilter;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\Values\ColorFilterValue as ApiColorFilterValue;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\Values\DefaultFilterValue as ApiDefaultFilterValue;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\Values\ImageFilterValue as ApiImageFilterValue;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\Values\RangeSliderValue as ApiRangeSliderValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\CategoryFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\ColorFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\ImageFilterValue;
use InvalidArgumentException;

abstract class Filter extends BaseFilter
{
    private const FILTER_RANGE_MIN = 'min';
    private const FILTER_RANGE_MAX = 'max';

    /** @var FilterValue[] */
    protected array $values;

    /**
     * Builds a new filter instance. May return null for unsupported filter types. Throws an exception for unknown
     * filter types.
     */
    public static function getInstance(ApiFilter $filter): ?Filter
    {
        switch (true) {
            case $filter instanceof ApiLabelFilter:
                if ($filter->getName() === BaseFilter::CAT_FILTER_NAME) {
                    return static::handleCategoryFilter($filter);
                }

                return static::handleLabelTextFilter($filter);
            case $filter instanceof ApiSelectFilter:
                if ($filter->getName() === BaseFilter::CAT_FILTER_NAME) {
                    return static::handleCategoryFilter($filter);
                }

                return static::handleSelectDropdownFilter($filter);
            case $filter instanceof ApiRangeSliderFilter:
                if ($filter->getName() === BaseFilter::RATING_FILTER_NAME) {
                    return static::handleRatingFilter($filter);
                }

                return static::handleRangeSliderFilter($filter);
            case $filter instanceof ApiColorFilter:
                return static::handleColorPickerFilter($filter);
            case $filter instanceof ApiImageFilter:
                return static::handleVendorImageFilter($filter);
            default:
                throw new InvalidArgumentException('The submitted filter is unknown.');
        }
    }

    public function addValue(FilterValue $filterValue): self
    {
        $this->values[] = $filterValue;

        return $this;
    }

    public function searchValue(string $needle): ?FilterValue
    {
        foreach ($this->values as $value) {
            if ($value->getName() === $needle) {
                return $value;
            }
        }

        return null;
    }

    private static function handleLabelTextFilter(ApiLabelFilter $filter): LabelTextFilter
    {
        $customFilter = new LabelTextFilter($filter->getName(), $filter->getDisplayName());

        foreach ($filter->getValues() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName(), $filter->getName()));
        }

        return $customFilter;
    }

    private static function handleSelectDropdownFilter(ApiSelectFilter $filter): SelectDropdownFilter
    {
        $customFilter = new SelectDropdownFilter($filter->getName(), $filter->getDisplayName());

        foreach ($filter->getValues() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName(), $filter->getName()));
        }

        return $customFilter;
    }

    private static function handleRangeSliderFilter(ApiRangeSliderFilter $filter): RangeSliderFilter
    {
        $customFilter = new RangeSliderFilter($filter->getName(), $filter->getDisplayName());
        $unit = $filter->getUnit();
        $step = $filter->getStepSize();

        if ($unit !== null) {
            $customFilter->setUnit($unit);
        }

        if ($step !== null) {
            $customFilter->setStep($step);
        }

        if ($filter->getTotalRange()) {
            $customFilter->setTotalRange([
                self::FILTER_RANGE_MIN => $filter->getTotalRange()->getMin(),
                self::FILTER_RANGE_MAX => $filter->getTotalRange()->getMax(),
            ]);
        }

        if ($filter->getSelectedRange()) {
            $customFilter->setSelectedRange([
                self::FILTER_RANGE_MIN => $filter->getSelectedRange()->getMin(),
                self::FILTER_RANGE_MAX => $filter->getSelectedRange()->getMax(),
            ]);
        }

        foreach ($filter->getValues() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName(), $filter->getName()));
        }

        if ($filter->getTotalRange()->getMin() && $filter->getTotalRange()->getMax()) {
            $customFilter->setMin($filter->getTotalRange()->getMin());
            $customFilter->setMax($filter->getTotalRange()->getMax());
        } else {
            /** @var ApiRangeSliderValue[] $filterItems */
            $filterItems = array_values($filter->getValues());

            $firstFilterItem = current($filterItems);
            if ($firstFilterItem?->getMin()) {
                $customFilter->setMin($firstFilterItem->getMin());
            }

            $lastFilterItem = end($filterItems);
            if ($lastFilterItem?->getMax()) {
                $customFilter->setMax($lastFilterItem->getMax());
            }
        }

        return $customFilter;
    }

    private static function handleColorPickerFilter(ApiColorFilter $filter): ColorPickerFilter
    {
        $customFilter = new ColorPickerFilter($filter->getName(), $filter->getDisplayName());

        /** @var ApiColorFilterValue $item */
        foreach ($filter->getValues() as $item) {
            $imageUrls[$item->getName()] = $item->getImage();

            $filterValue = new ColorFilterValue($item->getName(), $item->getName(), $filter->getName());
            $filterValue->setColorHexCode($item->getColor());

            self::setColorPickerDisplayType($item, $filterValue);

            $media = new Media($item->getImage());
            $filterValue->setMedia($media);

            $customFilter->addValue($filterValue);
        }

        return $customFilter;
    }

    private static function handleVendorImageFilter(ApiImageFilter $filter): VendorImageFilter
    {
        $customFilter = new VendorImageFilter($filter->getName(), $filter->getDisplayName());

        /** @var ApiImageFilterValue $item */
        foreach ($filter->getValues() as $item) {
            $imageUrls[$item->getName()] = $item->getImage();
            $filterValue = new ImageFilterValue($item->getName(), $item->getName(), $filter->getName());
            $media = new Media($item->getImage());
            $filterValue->setMedia($media);
            $customFilter->addValue($filterValue);
            $filterValue->setDisplayType('media');
        }

        return $customFilter;
    }

    private static function handleCategoryFilter(ApiLabelFilter|ApiSelectFilter $filter): CategoryFilter
    {
        $categoryFilter = new CategoryFilter($filter->getName(), $filter->getDisplayName());

        foreach ($filter->getValues() as $item) {
            $levels = explode('_', $item->getName());
            $currentValue = $categoryFilter;

            foreach ($levels as $level) {
                if (!$foundValue = $currentValue->searchValue($level)) {
                    $foundValue = new CategoryFilterValue($level, $level);
                    $foundValue->setSelected($item->isSelected());
                    $foundValue->setFrequency($item->getFrequency());

                    $currentValue->addValue($foundValue);
                }

                $currentValue = $foundValue;
            }
        }

        return $categoryFilter;
    }

    private static function handleRatingFilter(ApiRangeSliderFilter $filter): ?RatingFilter
    {
        $totalRange = $filter->getTotalRange();
        if ($totalRange->getMin() === $totalRange->getMax()) {
            return null;
        }

        $customFilter = new RatingFilter($filter->getName(), $filter->getDisplayName());

        if ($totalRange->getMax()) {
            $customFilter->setMaxPoints(ceil($totalRange->getMax()));
        }

        /** @var ApiRangeSliderValue $item */
        foreach ($filter->getValues() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName()));
        }

        return $customFilter;
    }

    private static function setColorPickerDisplayType(ApiColorFilterValue $item, ColorFilterValue $filterValue): void
    {
        if ($item->getImage() && trim($item->getImage()) !== '') {
            $filterValue->setDisplayType('media');
        } elseif ($item->getColor() && trim($item->getColor()) !== '') {
            $filterValue->setDisplayType('color');
        } else {
            $filterValue->setDisplayType('none');
        }
    }
}
