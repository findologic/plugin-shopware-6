<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response;

use FINDOLOGIC\Api\Responses\Xml21\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Promotion as ApiPromotion;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Filter;
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
        } elseif ($this->hasQuery($queryString)) {
            return $this->buildSearchTermQueryInfoMessage($queryString);
        } elseif ($this->isFilterSet($params, 'cat')) {
            return $this->buildCategoryQueryInfoMessage($params);
        } elseif ($this->isFilterSet($params, 'vendor')) {
            return $this->buildVendorQueryInfoMessage($params);
        } else {
            return QueryInfoMessage::buildInstance(QueryInfoMessage::TYPE_DEFAULT);
        }
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

        /** @var CategoryInfoMessage $categoryInfoMessage */
        $categoryInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_CATEGORY,
            null,
            $filters['cat']->getDisplay(),
            $category
        );

        return $categoryInfoMessage;
    }

    private function buildVendorQueryInfoMessage(array $params): VendorInfoMessage
    {
        $filters = array_merge($this->response->getMainFilters(), $this->response->getOtherFilters());

        /** @var VendorInfoMessage $vendorInfoMessage */
        $vendorInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_VENDOR,
            null,
            $filters['vendor']->getDisplay(),
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
}
