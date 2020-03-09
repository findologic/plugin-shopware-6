<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\FinSearch\Struct\Filter\CustomFilters;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

class FilterHandler
{
    protected const FILTER_DELIMITER = '|';

    protected const
        MIN_PREFIX = 'min-',
        MAX_PREFIX = 'max-';

    /**
     * Sets all requested filters to the FINDOLOGIC API request.
     *
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     * @param SearchNavigationRequest $searchNavigationRequest
     */
    public function handleFilters(ShopwareEvent $event, SearchNavigationRequest $searchNavigationRequest): void
    {
        $request = $event->getRequest();
        $selectedFilters = $request->query->all();
        $availableFilterNames = $this->fetchAvailableFilterNames($event);

        if ($selectedFilters) {
            foreach ($selectedFilters as $filterName => $filterValues) {
                foreach ($this->getFilterValues($filterValues) as $filterValue) {
                    $this->handleFilter($filterName, $filterValue, $searchNavigationRequest, $availableFilterNames);
                }
            }
        }
    }

    /**
     * Handles FINDOLOGIC-specific query params like "attrib" or "catFilter".
     * If any of these parameters are submitted, an URI may be returned that contains the query parameters
     * in a Shopware-readable format. If no FINDOLOGIC params are submitted, null may be returned.
     *
     * E.g.
     * https://www.example.com/search?attrib%5Bvendor%5D%3DAdidas will return
     * https://www.example.com/search?manufacturer=Adidas
     *
     * @param Request $request
     * @return string|null
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
                $mappedParams['cat'] = $catFilter;
            }

            unset($queryParams['catFilter']);
        }

        if ($mappedParams === []) {
            return null;
        }

        $params = array_merge($queryParams, $mappedParams);

        return sprintf(
            '%s?%s',
            $request->getBasePath(),
            http_build_query($params, '', '&', PHP_QUERY_RFC3986)
        );
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

        if (in_array($filterName, $availableFilterNames, true)) {
            $searchNavigationRequest->addAttribute($filterName, $filterValue);
        }
    }

    /**
     * @param string $filterName
     * @param string|int|float $filterValue
     * @param SearchNavigationRequest $searchNavigationRequest
     */
    protected function handleRangeSliderFilter(
        string $filterName,
        $filterValue,
        SearchNavigationRequest $searchNavigationRequest
    ): void {
        if (substr($filterName, 0, strlen(self::MIN_PREFIX)) == self::MIN_PREFIX) {
            $filterName = substr($filterName, strlen(self::MIN_PREFIX));
            $searchNavigationRequest->addAttribute($filterName, $filterValue, 'min');
        } else {
            $filterName = substr($filterName, strlen(self::MAX_PREFIX));
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
     * @return string[]
     */
    protected function fetchAvailableFilterNames(ShopwareEvent $event): array
    {
        $availableFilters = [];
        /** @var CustomFilters $customFilters */
        $customFilters = $event->getCriteria()->getExtension('flFilters');

        $filters = $customFilters->getFilters();
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
     *
     * @param string $filterValues
     * @return array
     */
    protected function getFilterValues(string $filterValues): array
    {
        return explode(self::FILTER_DELIMITER, $filterValues);
    }

    private function isMinRangeSlider(string $name): bool
    {
        return substr($name, 0, strlen(self::MIN_PREFIX)) == self::MIN_PREFIX;
    }

    private function isMaxRangeSlider(string $name): bool
    {
        return substr($name, 0, strlen(self::MAX_PREFIX)) == self::MAX_PREFIX;
    }
}
