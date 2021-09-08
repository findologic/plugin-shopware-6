<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\CategoryFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\RatingFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\CategoryFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

use function array_merge;
use function end;
use function in_array;

class FilterHandler
{
    protected const FILTER_DELIMITER = '|';
    protected const MIN_PREFIX = 'min-';
    protected const MAX_PREFIX = 'max-';
    protected const IGNORE_LIST = ['pushAttrib'];

    /**
     * Sets all requested filters to the FINDOLOGIC API request.
     *
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     */
    public function handleFilters(ShopwareEvent $event, SearchNavigationRequest $searchNavigationRequest): void
    {
        $request = $event->getRequest();
        $selectedFilters = $request->query->all();
        $availableFilterNames = $this->fetchAvailableFilterNames($event);

        if ($selectedFilters) {
            foreach ($selectedFilters as $filterName => $filterValues) {
                if (in_array($filterName, self::IGNORE_LIST, false)) {
                    continue;
                }
                foreach ($this->getFilterValues($filterValues) as $filterValue) {
                    $this->handleFilter(
                        $filterName,
                        $filterValue,
                        $searchNavigationRequest,
                        $availableFilterNames
                    );
                }
            }
        }
    }

    /**
     * Handles FINDOLOGIC-specific query params like "attrib" or "catFilter".
     * If any of these parameters are submitted, an URI may be returned that contains the query parameters
     * in a Shopware-readable format. If no FINDOLOGIC params are submitted, null may be returned.
     * E.g.
     * https://www.example.com/search?attrib%5Bvendor%5D%3DAdidas will return
     * https://www.example.com/search?manufacturer=Adidas
     */
    public function handleFindologicSearchParams(Request $request): ?string
    {
        $queryParams = $request->query->all();
        $mappedParams = [];

        $attributes = $request->get('attrib');
        if ($attributes) {
            foreach ($attributes as $key => $attribute) {
                foreach ($attribute as $value) {
                    if (is_array($value)) {
                        $value = implode(self::FILTER_DELIMITER, $value);
                    }

                    $mappedParams[$key] = $value;
                }
            }

            unset($queryParams['attrib']);
        }

        $catFilter = $request->get('catFilter');
        if ($catFilter) {
            if (!empty($catFilter)) {
                if (is_array($catFilter)) {
                    $catFilter = end($catFilter);
                }
                $mappedParams[BaseFilter::CAT_FILTER_NAME] = $catFilter;
            }

            unset($queryParams['catFilter']);
        }

        if ($mappedParams === []) {
            return null;
        }

        $params = array_merge($queryParams, $mappedParams);

        return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    protected function handleFilter(
        string $filterName,
        string $filterValue,
        SearchNavigationRequest $searchNavigationRequest,
        array $availableFilterNames
    ): void {
        // Range Slider filters in Shopware are prefixed with min-/max-. We manually need to remove this and send
        // the appropriate parameters to our API.
        if ($this->isRangeSliderFilter($filterName)) {
            $this->handleRangeSliderFilter($filterName, $filterValue, $searchNavigationRequest);

            return;
        }

        if ($this->isRatingFilter($filterName)) {
            $searchNavigationRequest->addAttribute($filterName, $filterValue, 'min');

            return;
        }

        if (in_array($filterName, $availableFilterNames, true)) {
            // This resolves the SW-451 issue about filter value conflict in storefront
            if ($filterName !== BaseFilter::CAT_FILTER_NAME && $this->isPropertyFilter($filterName, $filterValue)) {
                $this->handlePropertyFilter($filterName, $filterValue, $searchNavigationRequest);
            } else {
                $searchNavigationRequest->addAttribute($filterName, $filterValue);
            }
        }
    }

    /**
     * @param string|int|float $filterValue
     */
    protected function handleRangeSliderFilter(
        string $filterName,
        $filterValue,
        SearchNavigationRequest $searchNavigationRequest
    ): void {
        if (mb_strpos($filterName, self::MIN_PREFIX) === 0) {
            $filterName = mb_substr($filterName, mb_strlen(self::MIN_PREFIX));
            $searchNavigationRequest->addAttribute($filterName, $filterValue, 'min');
        } else {
            $filterName = mb_substr($filterName, mb_strlen(self::MAX_PREFIX));
            $searchNavigationRequest->addAttribute($filterName, $filterValue, 'max');
        }
    }

    protected function isRangeSliderFilter(string $name): bool
    {
        return $this->isMinRangeSlider($name) || $this->isMaxRangeSlider($name);
    }

    /**
     * Fetches all available filter names. This is needed to distinguish between standard Shopware query parameters
     * like "q", "sort", etc. and real filters.
     *
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     *
     * @return string[]
     */
    protected function fetchAvailableFilterNames(ShopwareEvent $event): array
    {
        $availableFilters = [];
        /** @var FiltersExtension $filtersExtension */
        $filtersExtension = $event->getCriteria()->getExtension('flFilters');

        $filters = $filtersExtension->getFilters();
        foreach ($filters as $filter) {
            $availableFilters[] = $filter->getId();
        }

        return $availableFilters;
    }

    /**
     * Submitting multiple filter values for the same filter e.g. size=20 and size=21, will not set
     * the same query parameter twice. Instead they have the same key and their values are
     * imploded via a special character (|). The query parameter looks like ?size=20|21.
     * This method simply explodes the given string into filter values.
     */
    protected function getFilterValues(string $filterValues): array
    {
        return explode(self::FILTER_DELIMITER, $filterValues);
    }

    private function isMinRangeSlider(string $name): bool
    {
        return mb_strpos($name, self::MIN_PREFIX) === 0;
    }

    private function isMaxRangeSlider(string $name): bool
    {
        return mb_strpos($name, self::MAX_PREFIX) === 0;
    }

    private function isRatingFilter(string $filterName): bool
    {
        return $filterName === BaseFilter::RATING_FILTER_NAME;
    }

    private function isPropertyFilter(string $filterName, string $filterValue): bool
    {
        return mb_strpos($filterValue, sprintf('%s%s', $filterName, FilterValue::DELIMITER)) === 0;
    }

    private function handlePropertyFilter(
        string $filterName,
        string $filterValue,
        SearchNavigationRequest $searchNavigationRequest
    ): void {
        $parsedFilterValue = explode(sprintf('%s%s', $filterName, FilterValue::DELIMITER), $filterValue);
        $filterValue = end($parsedFilterValue);
        $searchNavigationRequest->addAttribute($filterName, $filterValue);
    }

    public function handleAvailableFilters(ShopwareEvent $event): array
    {
        $criteria = $event->getCriteria();

        /** @var FiltersExtension $availableFilters */
        $availableFilters = $criteria->getExtension('flAvailableFilters');
        $allFilters = $criteria->getExtension('flFilters');

        return $this->parseFindologicFiltersForShopware($availableFilters, $allFilters);
    }

    private function parseFindologicFiltersForShopware(
        FiltersExtension $availableFilters,
        FiltersExtension $allFilters
    ): array {
        $result = [];
        $result[RatingFilter::RATING_FILTER_NAME]['max'] = 0;

        foreach ($allFilters->getFilters() as $filterWithAllValues) {
            $filterName = $filterWithAllValues->getId();
            if (!$filter = $availableFilters->getFilter($filterName)) {
                $result[$filterName]['entities'] = [];
                continue;
            }

            /** @var FilterValue[] $values */
            $values = $filter->getValues();

            if ($filter instanceof RatingFilter) {
                $max = end($values);
                $result[RatingFilter::RATING_FILTER_NAME]['max'] = $max->getId();
            } else {
                $filterValues = [];

                if ($filter instanceof CategoryFilter) {
                    $this->handleCategoryFilters($values, $filterValues);
                } else {
                    foreach ($values as $value) {
                        $valueId = $value->getUuid() ?? $value->getId();
                        $filterValues[] = [
                            'id' => $valueId,
                            'translated' => ['name' => $valueId]
                        ];
                        $filterValues[] = [
                            'id' => $value->getTranslated()->getName(),
                            'translated' => ['name' => $value->getTranslated()->getName()]
                        ];
                    }
                }

                $entityValues = [
                    'translated' => [
                        'name' => $filter instanceof CategoryFilter ? $filter->getId() : $filter->getName()
                    ],
                    'options' => $filterValues
                ];

                $result[$filterName]['entities'][] = $entityValues;
            }
        }

        $actualResult['properties']['entities'] = $result;

        return array_merge($actualResult, $result);
    }

    /**
     * @param FilterValue[] $values
     * @param array<string,array> $filterValues
     */
    private function handleCategoryFilters(array $values, array &$filterValues): void
    {
        /** @var CategoryFilterValue $value */
        foreach ($values as $value) {
            $valueId = $value->getId();
            $filterValues[] = [
                'id' => $valueId,
                'translated' => ['name' => $valueId]
            ];
            if ($value->getValues()) {
                $this->handleCategoryFilters($value->getValues(), $filterValues);
            }
        }
    }
}
