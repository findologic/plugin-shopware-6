<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response;

use FINDOLOGIC\Api\Responses\Xml21\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Promotion as ApiPromotion;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\CategoryFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Filter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\VendorImageFilter;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
use FINDOLOGIC\FinSearch\Struct\LandingPage as LandingPageExtension;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\CategoryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\QueryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\SearchTermQueryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\VendorInfoMessage;
use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
use GuzzleHttp\Client;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

class Xml21ResponseParser extends ResponseParser
{
    /** @var Xml21Response */
    protected $response;

    public function getProductIds(): array
    {
        return array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $this->response->getProducts()
        );
    }

    public function getSmartDidYouMeanExtension(Request $request): SmartDidYouMean
    {
        $query = $this->response->getQuery();

        $originalQuery = $query->getOriginalQuery() ? $query->getOriginalQuery()->getValue() : '';
        $alternativeQuery = $query->getAlternativeQuery();
        $didYouMeanQuery = $query->getDidYouMeanQuery();
        $type = $query->getQueryString()->getType();

        return new SmartDidYouMean(
            $originalQuery,
            $alternativeQuery,
            $didYouMeanQuery,
            $type,
            $request->getRequestUri()
        );
    }

    public function getLandingPageExtension(): ?LandingPageExtension
    {
        $landingPage = $this->response->getLandingPage();
        if ($landingPage instanceof LandingPage) {
            return new LandingPageExtension($landingPage->getLink());
        }

        return null;
    }

    public function getPromotionExtension(): ?Promotion
    {
        $promotion = $this->response->getPromotion();

        if ($promotion instanceof ApiPromotion) {
            return new Promotion($promotion->getImage(), $promotion->getLink());
        }

        return null;
    }

    public function getFiltersExtension(?Client $client = null): FiltersExtension
    {
        $apiFilters = array_merge($this->response->getMainFilters(), $this->response->getOtherFilters());

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
        return new Pagination($limit, $offset, $this->response->getResults()->getCount());
    }

    public function getQueryInfoMessage(ShopwareEvent $event): QueryInfoMessage
    {
        $queryString = $this->response->getQuery()->getQueryString()->getValue();
        $params = $event->getRequest()->query->all();

        if ($this->hasAlternativeQuery($queryString)) {
            /** @var SmartDidYouMean $smartDidYouMean */
            $smartDidYouMean = $event->getContext()->getExtension('flSmartDidYouMean');

            return $this->buildSearchTermQueryInfoMessage($smartDidYouMean->getAlternativeQuery());
        }

        if ($this->hasQuery($queryString)) {
            return $this->buildSearchTermQueryInfoMessage($queryString);
        }

        if ($this->isFilterSet($params, 'cat')) {
            return $this->buildCategoryQueryInfoMessage($params);
        }

        if ($this->isFilterSet($params, 'vendor')) {
            return $this->buildVendorQueryInfoMessage($params);
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

    private function buildCategoryQueryInfoMessage(array $params): CategoryInfoMessage
    {
        $filters = array_merge($this->response->getMainFilters(), $this->response->getOtherFilters());

        $categories = explode('_', $params['cat']);
        $category = end($categories);

        if (isset($filters['cat'])) {
            $filterName = $filters['cat']->getDisplay();
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

    private function buildVendorQueryInfoMessage(array $params): VendorInfoMessage
    {
        $filters = array_merge($this->response->getMainFilters(), $this->response->getOtherFilters());

        if (isset($filters['vendor'])) {
            $filterName = $filters['vendor']->getDisplay();
        } else {
            $filterName = $this->serviceConfigResource->getSmartSuggestBlocks($this->config->getShopkey())['vendor'];
        }

        /** @var VendorInfoMessage $vendorInfoMessage */
        $vendorInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_VENDOR,
            null,
            $filterName,
            $params['vendor']
        );

        return $vendorInfoMessage;
    }

    private function hasAlternativeQuery(?string $queryString): bool
    {
        $queryStringType = $this->response->getQuery()->getQueryString()->getType();

        return !empty($queryString) && (($queryStringType === 'corrected') || ($queryStringType === 'improved'));
    }

    private function hasQuery(?string $queryString): bool
    {
        return !empty($queryString);
    }

    private function isFilterSet(array $params, string $name): bool
    {
        return isset($params[$name]) && !empty($params[$name]);
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
            if ($filter instanceof VendorImageFilter) {
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

        if ($allowVendorHiddenFilter && $this->isFilterSet($params, 'vendor')) {
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
