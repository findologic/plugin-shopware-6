<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\CategoryFilter as ApiCategoryFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\ColorPickerFilter as ApiColorPickerFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Filter as ApiFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\ColorItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\DefaultItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\RangeSliderItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\LabelTextFilter as ApiLabelTextFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\RangeSliderFilter as ApiRangeSliderFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\SelectDropdownFilter as ApiSelectDropdownFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\VendorImageFilter as ApiVendorImageFilter;
use FINDOLOGIC\FinSearch\Struct\Filter\FilterValues\ColorImageFilterValue;
use FINDOLOGIC\FinSearch\Struct\Filter\FilterValues\FilterValueImageHandler;
use FINDOLOGIC\FinSearch\Struct\Filter\FilterValues\FilterValue;
use InvalidArgumentException;
use Shopware\Core\Framework\Struct\Struct;

abstract class Filter extends Struct
{
    /** @var string */
    private $id;

    /** @var string */
    private $name;

    /** @var FilterValue[] */
    private $values;

    /**
     * @param string $id
     * @param string $name
     * @param FilterValue[] $values
     */
    public function __construct(string $id, string $name, array $values = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->values = $values;
    }

    /**
     * Builds a new filter instance. May return null for unsupported filter types. Throws an exception for unknown
     * filter types.
     *
     * @param ApiFilter $filter
     *
     * @return Filter|null
     */
    public static function getInstance(ApiFilter $filter): ?Filter
    {
        switch (true) {
            case $filter instanceof ApiLabelTextFilter:
                return static::handleLabelTextFilter($filter);
            case $filter instanceof ApiSelectDropdownFilter:
                return static::handleSelectDropdownFilter($filter);
            case $filter instanceof ApiRangeSliderFilter:
                return static::handleRangeSliderFilter($filter);
            case $filter instanceof ApiColorPickerFilter:
                return static::handleColorPickerFilter($filter);
            case $filter instanceof ApiVendorImageFilter: // Needs manual implementation.
            case $filter instanceof ApiCategoryFilter: // Shopware does not have a category filter yet.
                return null;
            default:
                throw new InvalidArgumentException('The submitted filter is unknown.');
        }
    }

    private static function handleLabelTextFilter(ApiLabelTextFilter $filter): LabelTextFilter
    {
        $customFilter = new LabelTextFilter($filter->getName(), $filter->getDisplay());

        /** @var DefaultItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName()));
        }

        return $customFilter;
    }

    private static function handleSelectDropdownFilter(ApiSelectDropdownFilter $filter): SelectDropdownFilter
    {
        $customFilter = new SelectDropdownFilter($filter->getName(), $filter->getDisplay());

        /** @var DefaultItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName()));
        }

        return $customFilter;
    }

    private static function handleRangeSliderFilter(ApiRangeSliderFilter $filter): RangeSliderFilter
    {
        $customFilter = new RangeSliderFilter($filter->getName(), $filter->getDisplay());
        $customFilter->setUnit($filter->getAttributes()->getUnit());

        /** @var RangeSliderItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName()));
        }

        return $customFilter;
    }

    private static function handleColorPickerFilter(ApiColorPickerFilter $filter): ColorPickerFilter
    {
        $customFilter = new ColorPickerFilter($filter->getName(), $filter->getDisplay());

        $urls = [];
        /** @var ColorItem $item */
        foreach ($filter->getItems() as $item) {
            $urls[$item->getName()] = $item->getImage();

            $filterValue = new ColorImageFilterValue($item->getName(), $item->getName());
            $filterValue->setColorHexCode($item->getColor());

            $media = new Media($item->getImage());
            $filterValue->setMedia($media);

            $customFilter->addValue($filterValue);
        }

        $filterImageHandler = new FilterValueImageHandler();
        $validImages = $filterImageHandler->getValidImageUrls($urls);

        foreach ($customFilter->getValues() as $filterValue) {
            if (in_array($filterValue->getMedia()->getUrl(), $validImages)) {
                $filterValue->setDisplayType('media');
            }
        }

        return $customFilter;
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

    public function addValue(FilterValue $filterValue): self
    {
        $this->values[] = $filterValue;

        return $this;
    }
}
