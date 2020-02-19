<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\DefaultItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\LabelTextFilter as ApiLabelTextFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Filter\CustomFilters;
use FINDOLOGIC\FinSearch\Struct\Filter\Filter;
use FINDOLOGIC\FinSearch\Struct\Filter\FilterValue;
use FINDOLOGIC\FinSearch\Struct\Filter\LabelTextFilter;
use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;

abstract class SearchNavigationRequestHandler
{
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
    }

    abstract public function handleRequest(ShopwareEvent $event): void;

    /**
     * Sends a request to the FINDOLOGIC service based on the given event and the responsible request handler.
     *
     * @param ShopwareEvent $event
     * @param int|null $limit Limited amount of products.
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
     * @return int[]
     */
    protected function parseProductIdsFromResponse(Response $response): array
    {
        return array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $response->getProducts()
        );
    }

    protected function assignCriteriaToEvent(ShopwareEvent $event, Criteria $criteria): void
    {
        $event->getCriteria()->assign($criteria->getVars());
    }

    /**
     * @param Xml21Response $response
     * @param Criteria $criteria
     */
    protected function handleFilters(Xml21Response $response, Criteria $criteria): void
    {
        $filters = array_merge($response->getMainFilters(), $response->getOtherFilters());

        $customFilters = new CustomFilters();
        foreach ($filters as $filter) {
            $customFilter = Filter::getInstance($filter);

            if ($customFilter) {
                $customFilters->addFilter($customFilter);
            }
        }

        $criteria->addExtension(
            'flFilters',
            $customFilters
        );
    }
}
