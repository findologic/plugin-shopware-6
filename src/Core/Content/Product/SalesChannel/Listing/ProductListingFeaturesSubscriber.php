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
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Struct\SystemAware;
use FINDOLOGIC\FinSearch\Traits\SearchResultHelper;
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
    use SearchResultHelper;

    /** @var string FINDOLOGIC default sort for categories */
    public const DEFAULT_SORT = 'score';
    public const DEFAULT_SEARCH_SORT = 'score';
    /** @var int We do not need any products for a filter-only request. */
    private const RESULT_LIMIT_FILTER = 0;

    /** @var ProductListingSortingRegistry */
    protected $sortingRegistry;

    /** @var NavigationRequestFactory */
    protected $navigationRequestFactory;

    /** @var SearchRequestFactory */
    protected $searchRequestFactory;

    /** @var ServiceConfigResource */
    protected $serviceConfigResource;

    /** @var Config */
    protected $config;

    /** @var ApiConfig */
    protected $apiConfig;

    /** @var ContainerInterface */
    protected $container;

    /** @var SearchRequestHandler */
    protected $searchRequestHandler;

    /** @var NavigationRequestHandler */
    protected $navigationRequestHandler;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $optionRepository,
        EntityRepositoryInterface $productSortingRepository,
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

        parent::__construct(
            $connection,
            $optionRepository,
            $productSortingRepository,
            $systemConfigService,
            $sortingRegistry
        );
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        parent::handleResult($event);

        if (!$event->getContext()->getExtension('findologicService')->getEnabled()) {
            return;
        }

        $this->addTopResultSorting($event);
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        // Manually get the limit
        $limit = $event->getCriteria()->getLimit();
        parent::handleListingRequest($event);

        if ($this->allowRequest($event)) {
            // Set the limit here after the parent call as the parent call will override and the default Shopware limit
            // will be used otherwise
            $event->getCriteria()->setLimit($limit);
            $event->getCriteria()->setOffset($this->getOffset($event->getRequest(), $limit));

            $this->apiConfig->setServiceId($this->config->getShopkey());
            $this->handleFilters($event);
            $this->navigationRequestHandler->handleRequest($event);
            $this->setSystemAwareExtension($event);
        }
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        // Manually get the limit
        $limit = $event->getCriteria()->getLimit();
        parent::handleSearchRequest($event);

        if ($this->allowRequest($event)) {
            // Set the limit here after the parent call as the parent call will override and the default Shopware limit
            // will be used otherwise.
            $event->getCriteria()->setLimit($limit);
            $event->getCriteria()->setOffset($this->getOffset($event->getRequest(), $limit));

            $this->apiConfig->setServiceId($this->config->getShopkey());
            $this->handleFilters($event);
            $this->searchRequestHandler->handleRequest($event);
            $this->setSystemAwareExtension($event);
        }
    }

    protected function setSystemAwareExtension(ShopwareEvent $event): void
    {
        /** @var SystemAware $systemAware */
        $systemAware = $this->container->get(SystemAware::class);

        $event->getContext()->addExtension(SystemAware::IDENTIFIER, $systemAware);
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
            $responseParser = ResponseParser::getInstance(
                $response,
                $this->serviceConfigResource,
                $this->config
            );
            $filters = $responseParser->getFiltersExtension();
            $filtersWithSmartSuggestBlocks = $responseParser->getFiltersWithSmartSuggestBlocks(
                $filters,
                $this->serviceConfigResource->getSmartSuggestBlocks($this->config->getShopkey()),
                $event->getRequest()->query->all()
            );

            $event->getCriteria()->addExtension('flFilters', $filtersWithSmartSuggestBlocks);
        } catch (ServiceNotAliveException $e) {
            /** @var FindologicService $findologicService */
            $findologicService = $event->getContext()->getExtension('findologicService');
            $findologicService->setDisabled();
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

        $findologicService = new FindologicService();
        $event->getContext()->addExtension('findologicService', $findologicService);
        $findologicService->setEnabled();

        $isCategoryPage = !($event instanceof ProductSearchCriteriaEvent);
        if (!$this->config->isActive() || ($isCategoryPage && !$this->config->isActiveOnCategoryPages())) {
            $findologicService->setDisabled();

            return false;
        }

        $shopkey = $this->config->getShopkey();
        $isDirectIntegration = $this->serviceConfigResource->isDirectIntegration($shopkey);
        $isStagingShop = $this->serviceConfigResource->isStaging($shopkey);
        $isStagingSession = Utils::isStagingSession($event->getRequest());

        // Allow request if shop is not staging or is staging with findologic=on flag set
        $allowRequestForStaging = (!$isStagingShop || ($isStagingShop && $isStagingSession));

        if (!$isDirectIntegration && $allowRequestForStaging) {
            $shouldHandleRequest = true;
            $findologicService->setEnabled();
        } else {
            $shouldHandleRequest = false;
            $findologicService->setDisabled();
        }

        return $shouldHandleRequest;
    }
}
