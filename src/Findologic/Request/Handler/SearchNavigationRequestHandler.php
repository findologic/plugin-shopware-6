<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
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
     * @throws ServiceNotAliveException
     */
    public function sendRequest(SearchNavigationRequest $searchNavigationRequest): Response
    {
        $this->apiConfig->setServiceId($this->config->getShopkey());
        return $this->apiClient->send($searchNavigationRequest);
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
}
