<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use GuzzleHttp\Client;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber as ShopwareProductListingFeaturesSubscriber;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingFeaturesSubscriber extends ShopwareProductListingFeaturesSubscriber
{
    /** @var string FINDOLOGIC default sort for categories */
    public const DEFAULT_SORT = 'score';

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

    /** @var ApiClient */
    private $apiClient;

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
        $key = $request->get('sort', $default);

        if (!$key) {
            return null;
        }

        if ($this->sortingRegistry->has($key)) {
            return $key;
        }

        return $default;
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {


        parent::handleListingRequest($event);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        parent::handleSearchRequest($event);
        // TODO Add filters to criteria from FINDOLOGIC Response
    }
}
