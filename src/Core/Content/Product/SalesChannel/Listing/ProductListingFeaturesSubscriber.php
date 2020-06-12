<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\FinSearch\Exceptions\UnknownCategoryException;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\GuzzleHttp\Client;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber
    as ShopwareProductListingFeaturesSubscriber;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingFeaturesSubscriber extends ShopwareProductListingFeaturesSubscriber
{
    /** @var string FINDOLOGIC default sort for categories */
    public const DEFAULT_SORT = 'score';
    public const DEFAULT_SEARCH_SORT = 'score';
    /** @var int We do not need any products for a filter-only request. */
    private const RESULT_LIMIT_FILTER = 0;

    /** @var ProductListingSortingRegistry */
    private $sortingRegistry;

    /** @var NavigationRequestFactory */
    private $navigationRequestFactory;

    /** @var SearchRequestFactory */
    private $searchRequestFactory;

    /** @var ServiceConfigResource */
    private $serviceConfigResource;

    /** @var Config */
    private $config;

    /** @var ApiConfig */
    private $apiConfig;

    /** @var ContainerInterface */
    private $container;

    /** @var SearchRequestHandler */
    private $searchRequestHandler;

    /** @var NavigationRequestHandler */
    private $navigationRequestHandler;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $optionRepository,
        ProductListingSortingRegistry $sortingRegistry,
        NavigationRequestFactory $navigationRequestFactory,
        SearchRequestFactory $searchRequestFactory,
        SystemConfigService $systemConfigService,
        ServiceConfigResource $serviceConfigResource,
        GenericPageLoader $genericPageLoader,
        ContainerInterface $container,
        ?Config $config = null,
        ?ApiConfig $apiConfig = null,
        ?ApiClient $apiClient = null
    ) {
        // TODO: Check how we can improve the high amount of constructor arguments.
        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config ?? new Config($systemConfigService, $serviceConfigResource);
        $this->apiConfig = $apiConfig ?? new ApiConfig();
        $this->apiConfig->setHttpClient(new Client());
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
        $this->sortingRegistry = $sortingRegistry;
        $this->navigationRequestFactory = $navigationRequestFactory;
        $this->searchRequestFactory = $searchRequestFactory;
        parent::__construct($connection, $optionRepository, $sortingRegistry);
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        parent::handleResult($event);

        if (!$event->getContext()->getExtension('flEnabled')->getEnabled()) {
            return;
        }

        $this->addTopResultSorting($event);
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        parent::handleListingRequest($event);

        if ($this->allowRequest($event)) {
            $this->apiConfig->setServiceId($this->config->getShopkey());
            $this->handleFilters($event);
            $this->navigationRequestHandler->handleRequest($event);
        }
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        parent::handleSearchRequest($event);

        if ($this->allowRequest($event)) {
            $this->apiConfig->setServiceId($this->config->getShopkey());
            $this->handleFilters($event);
            $this->searchRequestHandler->handleRequest($event);
        }
    }

    private function addTopResultSorting(ProductListingResultEvent $event): void
    {
        $defaultSort = $event instanceof ProductSearchResultEvent ? self::DEFAULT_SEARCH_SORT : self::DEFAULT_SORT;
        $currentSorting = $this->getCurrentSorting($event->getRequest(), $defaultSort);

        $event->getResult()->setSorting($currentSorting);
        $this->sortingRegistry->add(
            new ProductListingSorting('score', 'filter.sortByScore', ['_score' => 'desc'])
        );
        $sortings = $this->sortingRegistry->getSortings();
        /** @var ProductListingSorting $sorting */
        foreach ($sortings as $sorting) {
            $sorting->setActive($sorting->getKey() === $currentSorting);
        }

        $event->getResult()->setSortings($sortings);
    }

    private function getCurrentSorting(Request $request, string $default): ?string
    {
        $key = $request->get('order', $default);
        if (Utils::versionLowerThan('6.2')) {
            $key = $request->get('sort', $default);
        }

        if (!$key) {
            return null;
        }

        if ($this->sortingRegistry->has($key)) {
            return $key;
        }

        return $default;
    }

    private function handleFilters(ProductListingCriteriaEvent $event): void
    {
        try {
            if ($event instanceof ProductSearchCriteriaEvent) {
                $response = $this->searchRequestHandler->doRequest($event, self::RESULT_LIMIT_FILTER);
            } else {
                $response = $this->navigationRequestHandler->doRequest($event, self::RESULT_LIMIT_FILTER);
            }
            $responseParser = ResponseParser::getInstance($response);
            $event->getCriteria()->addExtension('flFilters', $responseParser->getFiltersExtension());
        } catch (ServiceNotAliveException $e) {
            /** @var FindologicEnabled $flEnabled */
            $flEnabled = $event->getContext()->getExtension('flEnabled');
            $flEnabled->setDisabled();
        } catch (UnknownCategoryException $ignored) {
            // We ignore this exception and do not disable the plugin here, otherwise the autocomplete of Shopware
            // would be visible behind Findologic's search suggest
        }
    }

    /**
     * Checks if FINDOLOGIC should handle the request. Additionally may set configurations for future usage.
     *
     * @throws InvalidArgumentException
     */
    private function allowRequest(ProductListingCriteriaEvent $event): bool
    {
        if (!$this->config->isInitialized()) {
            $this->config->initializeBySalesChannel($event->getSalesChannelContext()->getSalesChannel()->getId());
        }

        $findologicEnabled = new FindologicEnabled();
        $event->getContext()->addExtension('flEnabled', $findologicEnabled);
        $findologicEnabled->setEnabled();

        $isCategoryPage = !($event instanceof ProductSearchCriteriaEvent);
        if (!$this->config->isActive() || ($isCategoryPage && !$this->config->isActiveOnCategoryPages())) {
            $findologicEnabled->setDisabled();

            return false;
        }

        $shopkey = $this->config->getShopkey();
        $isDirectIntegration = $this->serviceConfigResource->isDirectIntegration($shopkey);
        $isStagingShop = $this->serviceConfigResource->isStaging($shopkey);
        $isStagingSession = $this->isStagingSession($event);

        // Allow request if shop is not staging or is staging with findologic=on flag set
        $allowRequestForStaging = (!$isStagingShop || ($isStagingShop && $isStagingSession));

        if (!$isDirectIntegration && $allowRequestForStaging) {
            $shouldHandleRequest = true;
            $findologicEnabled->setEnabled();
        } else {
            $shouldHandleRequest = false;
            $findologicEnabled->setDisabled();
        }

        return $shouldHandleRequest;
    }

    private function isStagingSession(ShopwareEvent $event): bool
    {
        $request = $event->getRequest();

        $findologic = $request->get('findologic');

        if ($findologic === 'on') {
            $request->getSession()->set('stagingFlag', true);

            return true;
        }

        if ($findologic === 'off' || $findologic === 'disabled') {
            $request->getSession()->set('stagingFlag', false);

            return false;
        }

        if ($request->getSession()->get('stagingFlag') === true) {
            return true;
        }

        return false;
    }
}
