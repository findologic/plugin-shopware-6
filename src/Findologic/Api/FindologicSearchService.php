<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Api;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\FinSearch\Exceptions\Search\UnknownCategoryException;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchNavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Config as PluginConfig;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Struct\SystemAware;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FindologicSearchService
{
    private const FILTER_REQUEST_LIMIT = 0;

    /** @var ContainerInterface */
    private $container;

    /** @var ApiClient */
    private $apiClient;

    /** @var ApiConfig */
    private $apiConfig;

    /** @var PluginConfig */
    private $pluginConfig;

    /** @var GenericPageLoader */
    private $genericPageLoader;

    /** @var SortingService */
    private $sortingService;

    /** @var PaginationService */
    private $paginationService;

    public function __construct(
        ContainerInterface $container,
        ApiClient $apiClient,
        ApiConfig $apiConfig,
        PluginConfig $pluginConfig,
        GenericPageLoader $genericPageLoader,
        SortingService $sortingService,
        PaginationService $paginationService
    ) {
        $this->container = $container;
        $this->apiClient = $apiClient;
        $this->apiConfig = $apiConfig;
        $this->pluginConfig = $pluginConfig;
        $this->genericPageLoader = $genericPageLoader;
        $this->sortingService = $sortingService;
        $this->paginationService = $paginationService;
    }

    public function doSearch(ProductSearchCriteriaEvent $event, ?int $limitOverride = null): void
    {
        $limit = $limitOverride ?? $event->getCriteria()->getLimit();

        if ($this->allowRequest($event)) {
            $searchRequestHandler = $this->buildSearchRequestHandler();

            $this->handleRequest($event, $searchRequestHandler, $limit);
        }
    }

    public function doNavigation(ProductListingCriteriaEvent $event, ?int $limitOverride = null): void
    {
        $limit = $limitOverride ?? $event->getCriteria()->getLimit();

        if ($this->allowRequest($event)) {
            $navigationRequestHandler = $this->buildNavigationRequestHandler();
            if (!$this->isCategoryPage($navigationRequestHandler, $event)) {
                $this->disableFindologicService($event);

                return;
            }

            $this->handleRequest($event, $navigationRequestHandler, $limit);
        }
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        $findologicService = $event->getContext()->getExtension('findologicService');
        if (!$findologicService || !$findologicService->getEnabled()) {
            return;
        }

        $this->sortingService->handleResult($event);
    }

    protected function handleRequest(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler,
        ?int $limit
    ): void {
        $event->getCriteria()->setLimit($limit);
        $event->getCriteria()->setOffset($this->paginationService->getRequestOffset($event->getRequest(), $limit));

        $this->apiConfig->setServiceId($this->pluginConfig->getShopkey());
        $this->handleFilters($event, $requestHandler);
        $requestHandler->handleRequest($event);

        $this->setSystemAwareExtension($event);

        $this->sortingService->handleRequest($event, $requestHandler);
    }

    protected function allowRequest(ProductListingCriteriaEvent $event): bool
    {
        if (!$this->pluginConfig->isInitialized()) {
            $this->pluginConfig->initializeBySalesChannel($event->getSalesChannelContext());

            if ($this->pluginConfig->getShopkey()) {
                $this->apiConfig->setServiceId($this->pluginConfig->getShopkey());
            }
        }

        return Utils::shouldHandleRequest(
            $event->getRequest(),
            $event->getContext(),
            $this->container->get(ServiceConfigResource::class),
            $this->pluginConfig,
            !($event instanceof ProductSearchCriteriaEvent)
        );
    }

    protected function handleFilters(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler
    ): void {
        try {
            $response = $requestHandler->doRequest($event, self::FILTER_REQUEST_LIMIT);
            $filtersWithSmartSuggestBlocks = $this->parseFiltersFromResponse($response, $event);

            $event->getCriteria()->addExtension('flFilters', $filtersWithSmartSuggestBlocks);
        } catch (ServiceNotAliveException | UnknownCategoryException $e) {
            /** @var FindologicService $findologicService */
            $findologicService = $event->getContext()->getExtension('findologicService');
            $findologicService->disable();
            $findologicService->disableSmartSuggest();
        }
    }

    protected function buildSearchRequestHandler(): SearchRequestHandler
    {
        return new SearchRequestHandler(
            $this->container->get(ServiceConfigResource::class),
            $this->container->get(SearchRequestFactory::class),
            $this->pluginConfig,
            $this->apiConfig,
            $this->apiClient
        );
    }

    protected function buildNavigationRequestHandler(): NavigationRequestHandler
    {
        return new NavigationRequestHandler(
            $this->container->get(ServiceConfigResource::class),
            $this->container->get(NavigationRequestFactory::class),
            $this->pluginConfig,
            $this->apiConfig,
            $this->apiClient,
            $this->genericPageLoader,
            $this->container
        );
    }

    protected function setSystemAwareExtension(ShopwareEvent $event): void
    {
        /** @var SystemAware $systemAware */
        $systemAware = $this->container->get(SystemAware::class);

        $event->getContext()->addExtension(SystemAware::IDENTIFIER, $systemAware);
    }

    protected function isCategoryPage(NavigationRequestHandler $handler, ProductListingCriteriaEvent $event): bool
    {
        $isCategoryPage = $handler->fetchCategoryPath(
            $event->getRequest(),
            $event->getSalesChannelContext()
        );

        return !empty($isCategoryPage);
    }

    protected function disableFindologicService(ProductListingCriteriaEvent $event): void
    {
        /** @var FindologicService|null $findologicService */
        $findologicService = $event->getContext()->getExtension('findologicService');
        if (!$findologicService) {
            $findologicService = new FindologicService();
            $event->getContext()->addExtension('findologicService', $findologicService);
        }

        $findologicService->disable();
    }

    public function doFilter(ProductListingCriteriaEvent $event): void
    {
        if (!$this->allowRequest($event)) {
            return;
        }

        $handler = $this->buildNavigationRequestHandler();
        if (!$this->isCategoryPage($handler, $event)) {
            $handler = $this->buildSearchRequestHandler();
        }

        $this->handleFilters($event, $handler);
        $this->handleSelectableFilters($event, $handler, self::FILTER_REQUEST_LIMIT);
    }

    protected function handleSelectableFilters(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler,
        ?int $limit
    ): void {
        $response = $requestHandler->doRequest($event, $limit);
        $filtersWithSmartSuggestBlocks = $this->parseFiltersFromResponse($response, $event);

        $event->getCriteria()->addExtension('flAvailableFilters', $filtersWithSmartSuggestBlocks);
    }

    protected function parseFiltersFromResponse(
        Response $response,
        ProductListingCriteriaEvent $event
    ): FiltersExtension {
        $serviceConfigResource = $this->container->get(ServiceConfigResource::class);
        $responseParser = ResponseParser::getInstance(
            $response,
            $serviceConfigResource,
            $this->pluginConfig
        );
        $filters = $responseParser->getFiltersExtension();

        return $responseParser->getFiltersWithSmartSuggestBlocks(
            $filters,
            $serviceConfigResource->getSmartSuggestBlocks($this->pluginConfig->getShopkey()),
            $event->getRequest()->query->all()
        );
    }
}
