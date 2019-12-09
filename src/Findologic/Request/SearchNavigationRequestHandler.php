<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Promotion as ApiPromotion;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;

class SearchNavigationRequestHandler
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

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function sendRequest(
        ShopwareEvent $event,
        SearchNavigationRequest $searchNavigationRequest,
        ?Criteria $originalCriteria = null
    ): void {
        try {
            $response = $this->apiClient->send($searchNavigationRequest);
            $cleanCriteria = new Criteria($this->parseProductIdsFromResponse($response));

            $landingPage = $response->getLandingPage();
            if ($landingPage instanceof LandingPage) {
                header('Location: ' . $landingPage->getLink());
                exit;
            }

            $promotion = $response->getPromotion();
            if ($promotion instanceof ApiPromotion) {
                $promotion = new Promotion($promotion->getImage(), $promotion->getLink());
                $event->getContext()->addExtension('flPromotion', $promotion);
            }

            $this->assignCriteriaToEvent($event, $cleanCriteria);
        } catch (ServiceNotAliveException $e) {
            if ($originalCriteria !== null) {
                $this->assignCriteriaToEvent($event, $originalCriteria);
            }
        }
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
