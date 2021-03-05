<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\CategoryFilter as ApiCategoryFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\ColorPickerFilter as ApiColorPickerFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Filter as ApiFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\CategoryItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\ColorItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\DefaultItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\RangeSliderItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\VendorImageItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\LabelTextFilter as ApiLabelTextFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\RangeSliderFilter as ApiRangeSliderFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\SelectDropdownFilter as ApiSelectDropdownFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\VendorImageFilter as ApiVendorImageFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\FilterValueImageHandler;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\CategoryFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\ColorFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\ImageFilterValue;
use GuzzleHttp\Client;

abstract class Filter extends BaseFilter
{
    /** @var FilterValue[] */
    protected $values;

    /**
     * Builds a new filter instance. May return null for unsupported filter types. Throws an exception for unknown
     * filter types.
     */
    public static function getInstance(ApiFilter $filter): ?Filter
    {
        switch (true) {
            case $filter instanceof ApiLabelTextFilter:
                return static::handleLabelTextFilter($filter);
            case $filter instanceof ApiSelectDropdownFilter:
                return static::handleSelectDropdownFilter($filter);
            case $filter instanceof ApiRangeSliderFilter:
                if ($filter->getName() === BaseFilter::RATING_FILTER_NAME) {
                    return static::handleRatingFilter($filter);
                }

                return static::handleRangeSliderFilter($filter);
            case $filter instanceof ApiColorPickerFilter:
                return static::handleColorPickerFilter($filter);
            case $filter instanceof ApiVendorImageFilter:
                return static::handleVendorImageFilter($filter);
            case $filter instanceof ApiCategoryFilter:
                return static::handleCategoryFilter($filter);
            default:
                throw new \InvalidArgumentException('The submitted filter is unknown.');
        }
    }

    public function addValue(FilterValue $filterValue): self
    {
        $this->values[] = $filterValue;

        return $this;
    }

    private static function handleLabelTextFilter(ApiLabelTextFilter $filter): LabelTextFilter
    {
        $customFilter = new LabelTextFilter($filter->getName(), $filter->getDisplay());

        /** @var DefaultItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName(), $filter->getName()));
        }

        return $customFilter;
    }

    private static function handleSelectDropdownFilter(ApiSelectDropdownFilter $filter): SelectDropdownFilter
    {
        $customFilter = new SelectDropdownFilter($filter->getName(), $filter->getDisplay());

        /** @var DefaultItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName(), $filter->getName()));
        }

        return $customFilter;
    }

    private static function handleRangeSliderFilter(ApiRangeSliderFilter $filter): RangeSliderFilter
    {
        $customFilter = new RangeSliderFilter($filter->getName(), $filter->getDisplay());
        $unit = $filter->getAttributes()->getUnit();
        $step = $filter->getAttributes()->getStepSize();
        $attributes = $filter->getAttributes();

        if ($unit !== null) {
            $customFilter->setUnit($unit);
        }

        if ($step !== null) {
            $customFilter->setStep($step);
        }

        /** @var RangeSliderItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName(), $filter->getName()));
        }

        if ($attributes !== null) {
            $customFilter->setMin($attributes->getTotalRange()->getMin());
            $customFilter->setMax($attributes->getTotalRange()->getMax());
        } else {
            /** @var RangeSliderItem[] $filterItems */
            $filterItems = array_values($filter->getItems());

            $firstFilterItem = current($filterItems);
            if ($firstFilterItem && $firstFilterItem->getParameters()) {
                $customFilter->setMin($firstFilterItem->getParameters()->getMin());
            }

            $lastFilterItem = end($filterItems);
            if ($lastFilterItem && $lastFilterItem->getParameters()) {
                $customFilter->setMax($lastFilterItem->getParameters()->getMax());
            }
        }

        return $customFilter;
    }

    private static function handleColorPickerFilter(ApiColorPickerFilter $filter): ColorPickerFilter
    {
        $customFilter = new ColorPickerFilter($filter->getName(), $filter->getDisplay());

        /** @var ColorItem $item */
        foreach ($filter->getItems() as $item) {
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

    private static function handleVendorImageFilter(ApiVendorImageFilter $filter): VendorImageFilter
    {
        $customFilter = new VendorImageFilter($filter->getName(), $filter->getDisplay());

        /** @var VendorImageItem $item */
        foreach ($filter->getItems() as $item) {
            $imageUrls[$item->getName()] = $item->getImage();
            $filterValue = new ImageFilterValue($item->getName(), $item->getName(), $filter->getName());
            $media = new Media($item->getImage());
            $filterValue->setMedia($media);
            $customFilter->addValue($filterValue);
            $filterValue->setDisplayType('media');
        }

        return $customFilter;
    }

    private static function handleCategoryFilter(ApiCategoryFilter $filter): CategoryFilter
    {
        $customFilter = new CategoryFilter($filter->getName(), $filter->getDisplay());

        /** @var CategoryItem $item */
        foreach ($filter->getItems() as $item) {
            $filterValue = new CategoryFilterValue($item->getName(), $item->getName());
            $filterValue->setSelected($item->isSelected());
            $filterValue->setFrequency($item->getFrequency());
            self::parseSubFilters($filterValue, $item->getItems());

            $customFilter->addValue($filterValue);
        }

        return $customFilter;
    }

    /**
     * @param CategoryItem[] $items
     */
    private static function parseSubFilters(CategoryFilterValue $filterValue, array $items): void
    {
        foreach ($items as $item) {
            $filter = new CategoryFilterValue($item->getName(), $item->getName());
            $filter->setSelected($item->isSelected());
            $filter->setFrequency($item->getFrequency());
            self::parseSubFilters($filter, $item->getItems());

            $filterValue->addValue($filter);
        }
    }

    private static function handleRatingFilter(ApiRangeSliderFilter $filter): ?RatingFilter
    {
        $totalRange = $filter->getAttributes()->getTotalRange();
        if ($totalRange->getMin() === $totalRange->getMax()) {
            return null;
        }

        $customFilter = new RatingFilter($filter->getName(), $filter->getDisplay());
        $attributes = $filter->getAttributes();
        if ($attributes) {
            $customFilter->setMaxPoints(ceil($totalRange->getMax()));
        }

        /** @var RangeSliderItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName()));
        }

        return $customFilter;
    }

    private static function setColorPickerDisplayType(ColorItem $item, ColorFilterValue $filterValue): void
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
