<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Api;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\FinSearch\Exceptions\Search\UnknownCategoryException;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchNavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Config as PluginConfig;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Struct\SystemAware;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class FindologicSearchService
{
    private const DEFAULT_SORT = 'score';

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

    /** @var ProductListingSortingRegistry|null */
    private $legacySortingRegistry;

    public function __construct(
        ContainerInterface $container,
        ApiClient $apiClient,
        ApiConfig $apiConfig,
        PluginConfig $pluginConfig,
        GenericPageLoader $genericPageLoader,
        ?ProductListingSortingRegistry $legacySortingRegistry
    ) {
        $this->container = $container;
        $this->apiClient = $apiClient;
        $this->apiConfig = $apiConfig;
        $this->pluginConfig = $pluginConfig;
        $this->genericPageLoader = $genericPageLoader;
        $this->legacySortingRegistry = $legacySortingRegistry;
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

            $this->handleRequest($event, $navigationRequestHandler, $limit);
        }
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        if (!$event->getContext()->getExtension('findologicService')->getEnabled()) {
            return;
        }

        $this->addAdditionalSortingsLegacy($event);
    }

    protected function handleRequest(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler,
        ?int $limit
    ): void {
        $event->getCriteria()->setLimit($limit);
        $event->getCriteria()->setOffset($this->getOffset($event->getRequest(), $limit));

        $this->apiConfig->setServiceId($this->pluginConfig->getShopkey());
        $this->handleFilters($event, $requestHandler);
        $requestHandler->handleRequest($event);

        $this->setSystemAwareExtension($event);

        $this->addAdditionalSortings($event);
    }

    protected function allowRequest(ProductListingCriteriaEvent $event): bool
    {
        if (!$this->pluginConfig->isInitialized()) {
            $this->pluginConfig->initializeBySalesChannel($event->getSalesChannelContext()->getSalesChannel()->getId());
            $this->apiConfig->setServiceId($this->pluginConfig->getShopkey());
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
            $serviceConfigResource = $this->container->get(ServiceConfigResource::class);

            $responseParser = ResponseParser::getInstance(
                $response,
                $serviceConfigResource,
                $this->pluginConfig
            );
            $filters = $responseParser->getFiltersExtension();
            $filtersWithSmartSuggestBlocks = $responseParser->getFiltersWithSmartSuggestBlocks(
                $filters,
                $serviceConfigResource->getSmartSuggestBlocks($this->pluginConfig->getShopkey()),
                $event->getRequest()->query->all()
            );

            $event->getCriteria()->addExtension('flFilters', $filtersWithSmartSuggestBlocks);
        } catch (ServiceNotAliveException $e) {
            /** @var FindologicService $findologicService */
            $findologicService = $event->getContext()->getExtension('findologicService');
            $findologicService->disable();
        } catch (UnknownCategoryException $ignored) {
            // We ignore this exception and do not disable the plugin here, otherwise the autocomplete of Shopware
            // would be visible behind Findologic's search suggest
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

    protected function addAdditionalSortings(ProductListingCriteriaEvent $event): void
    {
        if (!Utils::versionLowerThan('6.3.3.0')) {
            $this->addTopResultSorting($event);
        }
    }

    protected function addAdditionalSortingsLegacy(ProductListingResultEvent $event): void
    {
        if (Utils::versionLowerThan('6.3.3.0')) {
            $this->addTopResultSortingLegacy($event);
        }
    }

    protected function getCurrentSorting(Request $request, string $default): ?string
    {
        $key = $request->get('order', $default);
        if (Utils::versionLowerThan('6.2')) {
            $key = $request->get('sort', $default);
        }

        if (!$key) {
            return null;
        }

        if ($this->legacySortingRegistry->has($key)) {
            return $key;
        }

        return $default;
    }

    protected function addTopResultSorting(ProductListingCriteriaEvent $event): void
    {
        /** @var TranslatorInterface $translator */
        $translator = $this->container->get('translator');

        /** @var ProductSortingCollection $availableSortings */
        $availableSortings = $event->getCriteria()->getExtension('sortings') ?? new ProductSortingCollection();

        $sortByScore = new ProductSortingEntity();
        $sortByScore->setId(Uuid::randomHex());
        $sortByScore->setActive(true);
        $sortByScore->setTranslated(['label' => $translator->trans('filter.sortByScore')]);
        $sortByScore->setKey('score');
        $sortByScore->setPriority(5);
        $sortByScore->setFields([
            [
                'field' => '_score',
                'order' => 'desc',
                'priority' => 1,
                'naturalSorting' => 0,
            ],
        ]);

        $availableSortings->add($sortByScore);

        $event->getCriteria()->addExtension('sortings', $availableSortings);
    }

    /**
     * Adds top result sorting for Shopware versions below 6.3.3.0.
     */
    protected function addTopResultSortingLegacy(ProductListingResultEvent $event): void
    {
        $currentSorting = $this->getCurrentSorting($event->getRequest(), self::DEFAULT_SORT);

        $event->getResult()->setSorting($currentSorting);
        $this->legacySortingRegistry->add(
            new ProductListingSorting('score', 'filter.sortByScore', ['_score' => 'desc'])
        );
        $sortings = $this->legacySortingRegistry->getSortings();
        /** @var ProductListingSorting $sorting */
        foreach ($sortings as $sorting) {
            $sorting->setActive($sorting->getKey() === $currentSorting);
        }

        $event->getResult()->setSortings($sortings);
    }

    protected function getOffset(Request $request, ?int $limit = null)
    {
        if (!$limit) {
            $limit = Pagination::DEFAULT_LIMIT;
        }

        $page = $this->getPage($request);

        return ($page - 1) * $limit;
    }

    protected function getPage(Request $request): int
    {
        $page = $request->query->getInt('p', 1);

        if ($request->isMethod(Request::METHOD_POST)) {
            $page = $request->request->getInt('p', $page);
        }

        return $page <= 0 ? 1 : $page;
    }

    protected function setSystemAwareExtension(ShopwareEvent $event): void
    {
        /** @var SystemAware $systemAware */
        $systemAware = $this->container->get(SystemAware::class);

        $event->getContext()->addExtension(SystemAware::IDENTIFIER, $systemAware);
    }
}
