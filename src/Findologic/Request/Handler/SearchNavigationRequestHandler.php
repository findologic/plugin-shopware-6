<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\PriceSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ProductNameSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ReleaseDateSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ScoreSortingHandler;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\SortingHandlerInterface;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

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
     * @param Request $request
     * @param SearchNavigationRequest $searchNavigationRequest
     */
    public function handleFilters(Request $request, SearchNavigationRequest $searchNavigationRequest): void
    {
        $attrib = $request->get('attrib', []);
        foreach ($attrib as $key => $attribute) {
            foreach ($attribute as $value) {
                $searchNavigationRequest->addAttribute($key, $value);
            }
        }

        $cat = $request->get('catFilter');
        if ($cat) {
            $searchNavigationRequest->addAttribute('cat', $cat);
        }
    }

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
     */
    protected function setPaginationParams(ShopwareEvent $event, SearchNavigationRequest $request): void
    {
        $request->setFirst($event->getCriteria()->getOffset());
        $request->setCount($event->getCriteria()->getLimit());
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
}
