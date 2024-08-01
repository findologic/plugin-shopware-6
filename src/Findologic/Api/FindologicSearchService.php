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
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SortingHandlerService;
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
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class FindologicSearchService
{
    private const FILTER_REQUEST_LIMIT = 0;

    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly ApiConfig $apiConfig,
        private readonly PluginConfig $pluginConfig,
        private readonly SortingService $sortingService,
        private readonly PaginationService $paginationService,
        private readonly SortingHandlerService $sortingHandlerService,
        private readonly ServiceConfigResource $serviceConfigResource,
        private readonly SearchRequestFactory $searchRequestFactory,
        private readonly NavigationRequestFactory $navigationRequestFactory,
        private readonly SystemAware $systemAware,
        private readonly EntityRepository $categoryRepository
    ) {
    }

    public function doSearch(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limitOverride = null
    ): void {
        $limit = $limitOverride ?? $criteria->getLimit();

        if ($this->allowRequest($request, $context)) {
            $searchRequestHandler = $this->buildSearchRequestHandler();

            $this->handleRequest($request, $criteria, $context, $searchRequestHandler, $limit);
        }
    }

    public function doNavigation(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limitOverride = null
    ): void {
        $limit = $limitOverride ?? $criteria->getLimit();

        if ($this->allowRequest($request, $context)) {
            $navigationRequestHandler = $this->buildNavigationRequestHandler();
            if (!$this->isCategoryPage($navigationRequestHandler, $request, $context)) {
                $this->disableFindologicService($context);

                return;
            }

            $this->handleRequest($request, $criteria, $context, $navigationRequestHandler, $limit);
        }
    }

    protected function handleRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        SearchNavigationRequestHandler $requestHandler,
        ?int $limit,
    ): void {
        $criteria->setLimit($limit);
        $criteria->setOffset($this->paginationService->getRequestOffset($request, $limit));

        $this->apiConfig->setServiceId($this->pluginConfig->getShopkey());
        $this->handleFilters($request, $criteria, $context, $requestHandler);
        $requestHandler->handleRequest($request, $criteria, $context);

        $this->setSystemAwareExtension($context);

        $this->sortingService->handleRequest($criteria, $requestHandler);
    }

    protected function allowRequest(Request $request, SalesChannelContext $context): bool
    {
        if (!$this->pluginConfig->isInitialized()) {
            $this->pluginConfig->initializeBySalesChannel($context);

            if ($this->pluginConfig->getShopkey()) {
                $this->apiConfig->setServiceId($this->pluginConfig->getShopkey());
            }
        }

        return Utils::shouldHandleRequest(
            $request,
            $context->getContext(),
            $this->serviceConfigResource,
            $this->pluginConfig,
            Utils::isNavigationPage($request)
        );
    }

    protected function handleFilters(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        SearchNavigationRequestHandler $requestHandler
    ): void {
        try {
            $response = $requestHandler->doRequest($request, $criteria, $context, self::FILTER_REQUEST_LIMIT);
            $filtersWithSmartSuggestBlocks = $this->parseFiltersFromResponse($response, $request);

            $criteria->addExtension('flFilters', $filtersWithSmartSuggestBlocks);
        } catch (ServiceNotAliveException | UnknownCategoryException $e) {
            /** @var FindologicService $findologicService */
            $findologicService = $context->getContext()->getExtension('findologicService');
            $findologicService->disable();
            $findologicService->disableSmartSuggest();
        }
    }

    protected function buildSearchRequestHandler(): SearchRequestHandler
    {
        return new SearchRequestHandler(
            $this->serviceConfigResource,
            $this->searchRequestFactory,
            $this->pluginConfig,
            $this->apiConfig,
            $this->apiClient,
            $this->sortingHandlerService
        );
    }

    protected function buildNavigationRequestHandler(): NavigationRequestHandler
    {
        return new NavigationRequestHandler(
            $this->categoryRepository,
            $this->serviceConfigResource,
            $this->navigationRequestFactory,
            $this->pluginConfig,
            $this->apiConfig,
            $this->apiClient,
            $this->sortingHandlerService,
        );
    }

    protected function setSystemAwareExtension(SalesChannelContext $context): void
    {
        $context->getContext()->addExtension(SystemAware::IDENTIFIER, $this->systemAware);
    }

    protected function isCategoryPage(
        NavigationRequestHandler $handler,
        Request $request,
        SalesChannelContext $context
    ): bool {
        $isCategoryPage = $handler->fetchCategoryPath($request, $context);

        return !empty($isCategoryPage);
    }

    protected function disableFindologicService(SalesChannelContext $context): void
    {
        /** @var FindologicService|null $findologicService */
        $findologicService = $context->getContext()->getExtension('findologicService');
        if (!$findologicService) {
            $findologicService = new FindologicService();
            $context->getContext()->addExtension('findologicService', $findologicService);
        }

        $findologicService->disable();
    }

    public function doFilter(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$this->allowRequest($request, $context)) {
            return;
        }

        $handler = $this->buildNavigationRequestHandler();
        if (!$this->isCategoryPage($handler, $request, $context)) {
            $handler = $this->buildSearchRequestHandler();
        }

        $this->handleFilters($request, $criteria, $context, $handler);
        $this->handleSelectableFilters($request, $criteria, $context, $handler, self::FILTER_REQUEST_LIMIT);
    }

    protected function handleSelectableFilters(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        SearchNavigationRequestHandler $requestHandler,
        ?int $limit
    ): void {
        $response = $requestHandler->doRequest($request, $criteria, $context, $limit);
        $filtersWithSmartSuggestBlocks = $this->parseFiltersFromResponse($response, $request);

        $criteria->addExtension('flAvailableFilters', $filtersWithSmartSuggestBlocks);
    }

    protected function parseFiltersFromResponse(
        Response $response,
        Request $request,
    ): FiltersExtension {
        $responseParser = ResponseParser::getInstance(
            $response,
            $this->serviceConfigResource,
            $this->pluginConfig
        );
        $filters = $responseParser->getFiltersExtension();

        return $responseParser->getFiltersWithSmartSuggestBlocks(
            $filters,
            $this->serviceConfigResource->getSmartSuggestBlocks($this->pluginConfig->getShopkey()),
            $request->query->all()
        );
    }
}
