<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Subscriber;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FrontendSubscriber implements EventSubscriberInterface
{
    /** @var Config */
    private $config;

    /** @var ServiceConfigResource */
    private $serviceConfigResource;

    /** @var ApiConfig */
    private $apiConfig;

    /** @var SearchRequestHandler */
    private $searchRequestHandler;

    /** @var NavigationRequestHandler */
    private $navigationRequestHandler;

    /** @var ContainerInterface */
    private $container;

    public function __construct(
        SystemConfigService $systemConfigService,
        ServiceConfigResource $serviceConfigResource,
        SearchRequestFactory $searchRequestFactory,
        NavigationRequestFactory $navigationRequestFactory,
        GenericPageLoader $genericPageLoader,
        ContainerInterface $container,
        ?Config $config = null,
        ?ApiConfig $apiConfig = null,
        ?ApiClient $apiClient = null
    ) {
        // Ensure that all classes were autoloaded / will be autoloaded.
        require_once __DIR__ . '/../../vendor/autoload.php';

        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config ?? new Config($systemConfigService, $serviceConfigResource);
        $this->apiConfig = $apiConfig ?? new ApiConfig();
        $apiClient = $apiClient ?? new ApiClient($this->apiConfig);
        $this->container = $container;

        $this->searchRequestHandler = new SearchRequestHandler(
            $this->serviceConfigResource,
            $searchRequestFactory,
            $this->config,
            $this->apiConfig,
            $apiClient
        );

        $this->navigationRequestHandler = new NavigationRequestHandler(
            $this->serviceConfigResource,
            $navigationRequestFactory,
            $this->config,
            $this->apiConfig,
            $apiClient,
            $genericPageLoader,
            $container
        );
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            HeaderPageletLoadedEvent::class => 'onHeaderLoaded',
            ProductEvents::PRODUCT_SEARCH_CRITERIA => 'onSearch',
            ProductEvents::PRODUCT_LISTING_CRITERIA => 'onNavigation'
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function onHeaderLoaded(HeaderPageletLoadedEvent $event): void
    {
        $this->config->initializeBySalesChannel($event->getSalesChannelContext()->getSalesChannel()->getId());

        // This will store the plugin config for usage in our templates
        $event->getPagelet()->addExtension('flConfig', $this->config);

        if (!$this->config->isActive()) {
            return;
        }

        $shopkey = $this->config->getShopkey();
        $customerGroupId = $event->getSalesChannelContext()->getCurrentCustomerGroup()->getId();
        $userGroupHash = Utils::calculateUserGroupHash($shopkey, $customerGroupId);
        $snippet = new Snippet(
            $shopkey,
            $this->config->getSearchResultContainer(),
            $this->config->getNavigationResultContainer(),
            $userGroupHash
        );

        // Save the snippet for usage in template
        $event->getPagelet()->addExtension('flSnippet', $snippet);
    }

    /**
     * @throws InvalidArgumentException
     * @throws CategoryNotFoundException
     * @throws MissingRequestParameterException
     * @throws InconsistentCriteriaIdsException
     */
    public function onNavigation(ProductListingCriteriaEvent $event): void
    {
        if ($this->allowRequest($event)) {
            $this->apiConfig->setServiceId($this->config->getShopkey());
            $this->navigationRequestHandler->handleRequest($event);
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function onSearch(ProductSearchCriteriaEvent $event): void
    {
        if ($this->allowRequest($event)) {
            $this->apiConfig->setServiceId($this->config->getShopkey());
            $this->searchRequestHandler->handleRequest($event);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function allowRequest(ProductListingCriteriaEvent $event): bool
    {
        if (!$this->config->isInitialized()) {
            $this->config->initializeBySalesChannel($event->getSalesChannelContext()->getSalesChannel()->getId());
        }

        if (!$this->config->isActive()) {
            return false;
        }

        $shopkey = $this->config->getShopkey();
        $isDirectIntegration = $this->serviceConfigResource->isDirectIntegration($shopkey);
        $isStagingShop = $this->serviceConfigResource->isStaging($shopkey);

        // If it is direct integration or a staging shop, then we do not allow the request
        return !($isDirectIntegration || $isStagingShop);
    }
}
