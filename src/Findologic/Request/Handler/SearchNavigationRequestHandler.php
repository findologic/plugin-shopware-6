<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\PriceSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ProductNameSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ReleaseDateSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ScoreSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\SortingHandlerInterface;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Filter\CustomFilters;
use FINDOLOGIC\FinSearch\Struct\Filter\Filter;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;

abstract class SearchNavigationRequestHandler
{
    public const NOT_ALLOWED_FILTERS = [
        'p',
        'sort'
    ];

    /**
     * @var ServiceConfigResource
     */
    protected $serviceConfigResource;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ApiConfig
     */
    protected $apiConfig;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var FindologicRequestFactory
     */
    protected $findologicRequestFactory;

    /**
     * @var SortingHandlerInterface[]
     */
    protected $sortingHandlers;

    public function __construct(
        ServiceConfigResource $serviceConfigResource,
        FindologicRequestFactory $findologicRequestFactory,
        Config $config,
        ApiConfig $apiConfig,
        ApiClient $apiClient
    ) {
        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config;
        $this->apiConfig = $apiConfig;
        $this->apiClient = $apiClient;
        $this->findologicRequestFactory = $findologicRequestFactory;

        $this->sortingHandlers = $this->getSortingHandler();
    }

    abstract public function handleRequest(ShopwareEvent $event): void;

    /**
     * Sends a request to the FINDOLOGIC service based on the given event and the responsible request handler.
     *
     * @param ShopwareEvent $event
     * @param int|null $limit Limited amount of products.
     *
     * @return Response|null
     */
    abstract public function doRequest(ShopwareEvent $event, ?int $limit = null): ?Response;

    /**
     * @throws ServiceNotAliveException
     */
    public function sendRequest(SearchNavigationRequest $searchNavigationRequest): Response
    {
        return $this->apiClient->send($searchNavigationRequest);
    }

    protected function handleFilters(ShopwareEvent $event, SearchNavigationRequest $searchNavigationRequest): void
    {
        $request = $event->getRequest();
        $params = $request->query->all();

        if (array_key_exists('attrib', $params)) {
            foreach ($params['attrib'] as $key => $attribute) {
                foreach ($attribute as $value) {
                    $searchNavigationRequest->addAttribute($key, $value);
                }
            }
            unset($params['attrib']);
        }

        if (array_key_exists('catFilter', $params)) {
            $cat = $params['catFilter'];
            if (!empty($cat)) {
                if (is_array($cat)) {
                    $cat = end($cat);
                }
                $searchNavigationRequest->addAttribute('cat', $cat);
            }
            unset($params['catFilter']);
        }

        $availableFilters = $this->fetchAvailableFilters($event);

        // Add any additional parameters that are filterable in the request
        foreach ($params as $key => $param) {
            if (!empty($param) && in_array($key, $availableFilters, false)) {
                $searchNavigationRequest->addAttribute($key, $param);
            }
        }
    }

    /**
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     * @param SearchNavigationRequest $request
     * @param int|null $limit
     */
    protected function setPaginationParams(ShopwareEvent $event, SearchNavigationRequest $request, ?int $limit): void
    {
        $request->setFirst($event->getCriteria()->getOffset());
        $request->setCount($limit ?? $event->getCriteria()->getLimit());
    }

    /**
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     * @param Criteria $criteria
     */
    protected function assignCriteriaToEvent(ShopwareEvent $event, Criteria $criteria): void
    {
        $event->getCriteria()->assign($criteria->getVars());
    }

    /**
     * @return SortingHandlerInterface[]
     */
    protected function getSortingHandler(): array
    {
        return [
            new ScoreSortingHandler(),
            new PriceSortingHandler(),
            new ProductNameSortingHandler(),
            new ReleaseDateSortingHandler()
        ];
    }

    protected function addSorting(SearchNavigationRequest $searchNavigationRequest, Criteria $criteria): void
    {
        foreach ($this->sortingHandlers as $handler) {
            foreach ($criteria->getSorting() as $fieldSorting) {
                if ($handler->supportsSorting($fieldSorting)) {
                    $handler->generateSorting($fieldSorting, $searchNavigationRequest);
                }
            }
        }
    }

    protected function setPagination(
        Criteria $criteria,
        ResponseParser $responseParser,
        ?int $limit,
        ?int $offset
    ): void {
        $pagination = $responseParser->getPaginationExtension($limit, $offset);
        $criteria->addExtension('flPagination', $pagination);
    }

    /**
     * @return string[]
     */
    private function fetchAvailableFilters(ShopwareEvent $event): array
    {
        $availableFilters = [];

        /** @var CustomFilters $customFilters */
        $customFilters = $event->getCriteria()->getExtension('flFilters');

        /** @var Filter[] $filters */
        $filters = $customFilters->getFilters();

        foreach ($filters as $filter) {
            $availableFilters[] = $filter->getId();
        }

        return $availableFilters;
    }
}
