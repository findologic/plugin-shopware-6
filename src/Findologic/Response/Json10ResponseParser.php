<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response;

use FINDOLOGIC\Api\Responses\Json10\Json10Response;
use FINDOLOGIC\Api\Responses\Json10\Properties\Filter\Filter as ApiFilter;
use FINDOLOGIC\Api\Responses\Json10\Properties\Item;
use FINDOLOGIC\Api\Responses\Json10\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Json10\Properties\Promotion as ApiPromotion;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\CategoryFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Filter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\VendorImageFilter;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
use FINDOLOGIC\FinSearch\Struct\LandingPage as LandingPageExtension;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\CategoryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\QueryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\SearchTermQueryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\ShoppingGuideInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\VendorInfoMessage;
use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
use GuzzleHttp\Client;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

class Json10ResponseParser extends ResponseParser
{
    /** @var Json10Response $response */
    protected Response $response;

    public function getProductIds(): array
    {
        return array_map(
            static function (Item $product) {
                return $product->getId();
            },
            $this->response->getResult()->getItems()
        );
    }

    public function getSmartDidYouMeanExtension(Request $request): SmartDidYouMean
    {
        $originalQuery = $this->response->getRequest()->getQuery() ?? '';
        $effectiveQuery = $this->response->getResult()->getMetadata()->getEffectiveQuery();
        $correctedQuery = $this->response->getResult()->getVariant()->getCorrectedQuery();
        $didYouMeanQuery = $this->response->getResult()->getVariant()->getDidYouMeanQuery();
        $improvedQuery = $this->response->getResult()->getVariant()->getImprovedQuery();

        return new SmartDidYouMean(
            $originalQuery,
            $effectiveQuery,
            $correctedQuery,
            $didYouMeanQuery,
            $improvedQuery,
            $request->getRequestUri()
        );
    }

    public function getLandingPageExtension(): ?LandingPageExtension
    {
        $landingPage = $this->response->getResult()->getMetadata()->getLandingPage();
        if ($landingPage instanceof LandingPage) {
            return new LandingPageExtension($landingPage->getUrl());
        }

        return null;
    }

    public function getPromotionExtension(): ?Promotion
    {
        $promotion = $this->response->getResult()->getMetadata()->getPromotion();

        if ($promotion instanceof ApiPromotion) {
            return new Promotion($promotion->getImageUrl(), $promotion->getUrl());
        }

        return null;
    }

    public function getFiltersExtension(?Client $client = null): FiltersExtension
    {
        $apiFilters = array_merge(
            $this->response->getResult()->getMainFilters() ?? [],
            $this->response->getResult()->getOtherFilters() ?? []
        );

        $filtersExtension = new FiltersExtension();
        foreach ($apiFilters as $apiFilter) {
            $filter = Filter::getInstance($apiFilter);

            if ($filter && count($filter->getValues()) >= 1) {
                $filtersExtension->addFilter($filter);
            }
        }

        return $filtersExtension;
    }

    public function getPaginationExtension(?int $limit, ?int $offset): Pagination
    {
        return new Pagination($limit, $offset, $this->response->getResult()->getMetadata()->getTotalResults());
    }

    public function getQueryInfoMessage(ShopwareEvent $event): QueryInfoMessage
    {
        $queryString = $this->response->getRequest()->getQuery() ?? '';
        $params = $event->getRequest()->query->all();

        if ($this->hasAlternativeQuery($queryString)) {
            /** @var SmartDidYouMean $smartDidYouMean */
            $smartDidYouMean = $event->getContext()->getExtension('flSmartDidYouMean');

            return $this->buildSearchTermQueryInfoMessage($smartDidYouMean->getEffectiveQuery());
        }

        // Check for shopping guide parameter first, otherwise it will always be overridden with search or vendor query
        if ($this->isFilterSet($params, 'wizard')) {
            return $this->buildShoppingGuideInfoMessage($params);
        }

        if ($this->hasQuery($queryString)) {
            return $this->buildSearchTermQueryInfoMessage($queryString);
        }

        if ($this->isFilterSet($params, 'cat')) {
            return $this->buildCategoryQueryInfoMessage($params);
        }

        $vendorFilterValues = $this->getFilterValues($params, BaseFilter::VENDOR_FILTER_NAME);
        if (
            $vendorFilterValues &&
            count($vendorFilterValues) === 1
        ) {
            return $this->buildVendorQueryInfoMessage($params, current($vendorFilterValues));
        }

        return QueryInfoMessage::buildInstance(QueryInfoMessage::TYPE_DEFAULT);
    }

    private function buildSearchTermQueryInfoMessage(string $query): SearchTermQueryInfoMessage
    {
        /** @var SearchTermQueryInfoMessage $queryInfoMessage */
        $queryInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_QUERY,
            $query
        );

        return $queryInfoMessage;
    }

    private function buildShoppingGuideInfoMessage(array $params): ShoppingGuideInfoMessage
    {
        /** @var ShoppingGuideInfoMessage $queryInfoMessage */
        $queryInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_SHOPPING_GUIDE,
            $params['wizard']
        );

        return $queryInfoMessage;
    }

    private function buildCategoryQueryInfoMessage(array $params): CategoryInfoMessage
    {
        /** @var ApiFilter[] $filters */
        $filters = array_merge(
            $this->response->getResult()->getMainFilters() ?? [],
            $this->response->getResult()->getOtherFilters() ?? []
        );

        $categories = explode('_', $params['cat']);
        $category = end($categories);

        $catFilter = array_filter(
            $filters,
            static fn (ApiFilter $filter) => $filter->getName() === Filter::CAT_FILTER_NAME
        );

        if ($catFilter && count($catFilter) === 1) {
            $filterName = array_values($catFilter)[0]->getDisplayName();
        } else {
            $filterName = $this->serviceConfigResource->getSmartSuggestBlocks($this->config->getShopkey())['cat'];
        }

        /** @var CategoryInfoMessage $categoryInfoMessage */
        $categoryInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_CATEGORY,
            null,
            $filterName,
            $category
        );

        return $categoryInfoMessage;
    }

    private function buildVendorQueryInfoMessage(array $params, string $value): VendorInfoMessage
    {
        /** @var ApiFilter[] $filters */
        $filters = array_merge(
            $this->response->getResult()->getMainFilters() ?? [],
            $this->response->getResult()->getOtherFilters() ?? []
        );

        $vendorFilter = array_filter(
            $filters,
            static fn (ApiFilter $filter) => $filter->getName() === BaseFilter::VENDOR_FILTER_NAME
        );

        if ($vendorFilter && count($vendorFilter) === 1) {
            $filterName = array_values($vendorFilter)[0]->getDisplayName();
        } else {
            $filterName = $this->serviceConfigResource->getSmartSuggestBlocks($this->config->getShopkey())['vendor'];
        }

        /** @var VendorInfoMessage $vendorInfoMessage */
        $vendorInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_VENDOR,
            null,
            $filterName,
            $value
        );

        return $vendorInfoMessage;
    }

    private function hasAlternativeQuery(?string $queryString): bool
    {
        $correctedQuery = $this->response->getResult()->getVariant()->getCorrectedQuery();
        $improvedQuery = $this->response->getResult()->getVariant()->getImprovedQuery();
        $didYouMeanQuery = $this->response->getResult()->getVariant()->getDidYouMeanQuery();

        return !empty($queryString) && ($correctedQuery || $improvedQuery || $didYouMeanQuery);
    }

    private function hasQuery(?string $queryString): bool
    {
        return !empty($queryString);
    }

    private function isFilterSet(array $params, string $name): bool
    {
        return isset($params[$name]) && !empty($params[$name]);
    }

    /**
     * @param array $params
     * @param string $name
     * @return string[]|null
     */
    private function getFilterValues(array $params, string $name): ?array
    {
        if (!$this->isFilterSet($params, $name)) {
            return null;
        }

        $filterValues = [];
        $joinedFilterValues = explode(FilterHandler::FILTER_DELIMITER, $params[$name]);

        foreach ($joinedFilterValues as $joinedFilterValue) {
            $filterValues[] = str_contains($joinedFilterValue, FilterValue::DELIMITER)
                ? explode(FilterValue::DELIMITER, $joinedFilterValue)[1]
                : $joinedFilterValue;
        }

        return $filterValues;
    }

    public function getFiltersWithSmartSuggestBlocks(
        FiltersExtension $flFilters,
        array $smartSuggestBlocks,
        array $params
    ): FiltersExtension {
        $hasCategoryFilter = $hasVendorFilter = false;

        foreach ($flFilters->getFilters() as $filter) {
            if ($filter instanceof CategoryFilter) {
                $hasCategoryFilter = true;
            }
            if ($filter->getId() === 'vendor') {
                $hasVendorFilter = true;
            }
        }

        $allowCatHiddenFilter = !$hasCategoryFilter && array_key_exists('cat', $smartSuggestBlocks);
        $allowVendorHiddenFilter = !$hasVendorFilter && array_key_exists('vendor', $smartSuggestBlocks);

        if ($allowCatHiddenFilter && $this->isFilterSet($params, 'cat')) {
            $customFilter = $this->buildHiddenFilter($smartSuggestBlocks, $params, 'cat');
            if ($customFilter) {
                $flFilters->addFilter($customFilter);
            }
        }

        if ($allowVendorHiddenFilter && $this->isFilterSet($params, BaseFilter::VENDOR_FILTER_NAME)) {
            $customFilter = $this->buildHiddenFilter($smartSuggestBlocks, $params, 'vendor');
            if ($customFilter) {
                $flFilters->addFilter($customFilter);
            }
        }

        return $flFilters;
    }

    /**
     * @param string[] $smartSuggestBlocks
     * @param string[] $params
     * @param string $filterName
     *
     * @return BaseFilter|null
     */
    private function buildHiddenFilter(array $smartSuggestBlocks, array $params, string $filterName): ?BaseFilter
    {
        $display = $smartSuggestBlocks[$filterName];
        $value = $params[$filterName];

        switch ($filterName) {
            case 'cat':
                $customFilter = new CategoryFilter($filterName, $display);
                break;
            case 'vendor':
                $customFilter = new VendorImageFilter($filterName, $display);
                break;
            default:
                return null;
        }

        $filterValue = new FilterValue($value, $value, $filterName);
        $customFilter->addValue($filterValue);
        $customFilter->setHidden(true);

        return $customFilter;
    }
}
