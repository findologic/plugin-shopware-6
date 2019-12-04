<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Event\NestedEvent;

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
     * @throws InvalidArgumentException
     */
    protected function handleRequest(NestedEvent $event)
    {
        if ($this->config->isActive()) {
            $shopkey = $this->config->getShopkey();
            $isDirectIntegration = $this->serviceConfigResource->isDirectIntegration($shopkey);
            $isStagingShop = $this->serviceConfigResource->isStaging($shopkey);

            if ($isDirectIntegration || $isStagingShop) {
                return;
            }

            $this->apiConfig->setServiceId($shopkey);
        }
    }
}
