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
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

abstract class SearchNavigationRequestHandler
{
    private const
        MIN_PREFIX = 'min-',
        MAX_PREFIX = 'max-';

    private const
        FILTER_DELIMITER = '|';

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

    /**
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     * @param SearchNavigationRequest $searchNavigationRequest
     */
    protected function handleFilters(ShopwareEvent $event, SearchNavigationRequest $searchNavigationRequest): void
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

    private function isRangeSliderFilter(string $name): bool
    {
        return $this->isMinRangeSlider($name) || $this->isMaxRangeSlider($name);
    }

    private function isMinRangeSlider(string $name): bool
    {
        return substr($name, 0, strlen(self::MIN_PREFIX)) == self::MIN_PREFIX;
    }

    private function isMaxRangeSlider(string $name): bool
    {
        return substr($name, 0, strlen(self::MAX_PREFIX)) == self::MAX_PREFIX;
    }

    private function handleFilter(
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
    private function handleRangeSliderFilter(
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

    protected function getFilterValues(string $filterValues): array
    {
        return explode(self::FILTER_DELIMITER, $filterValues);
    }

    /**
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     * @return string[]
     */
    private function fetchAvailableFilterNames(ShopwareEvent $event): array
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
}
