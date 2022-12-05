<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Storefront\Controller;

use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Storefront\Page\Search\SearchPageLoader as FindologicSearchPageLoader;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\LandingPage;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\SearchController as ShopwareSearchController;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends StorefrontController
{
    private ShopwareSearchController $decorated;

    private SearchPageLoader $searchPageLoader;

    private FilterHandler $filterHandler;

    private FindologicSearchService $findologicSearchService;

    private ServiceConfigResource $serviceConfigResource;

    private Config $config;

    public function __construct(
        ShopwareSearchController $decorated,
        ?SearchPageLoader $searchPageLoader,
        FilterHandler $filterHandler,
        ContainerInterface $container,
        FindologicSearchService $findologicSearchService,
        ServiceConfigResource $serviceConfigResource,
        FindologicConfigService $findologicConfigService
    ) {
        $this->container = $container;
        $this->decorated = $decorated;
        $this->searchPageLoader = $this->buildSearchPageLoader($searchPageLoader);
        $this->filterHandler = $filterHandler;
        $this->findologicSearchService = $findologicSearchService;
        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    private function buildSearchPageLoader(?SearchPageLoader $searchPageLoader): SearchPageLoader
    {
        if (!$searchPageLoader) {
            return $this->container->get(FindologicSearchPageLoader::class);
        }

        return $searchPageLoader;
    }

    /**
     * @HttpCache()
     * @RouteScope(scopes={"storefront"})
     * @Route("/search", name="frontend.search.page", methods={"GET"})
     */
    public function search(SalesChannelContext $context, Request $request): Response
    {
        if ($redirectResponse = $this->handleFindologicSearchParams($request)) {
            return $redirectResponse;
        }

        $page = $this->searchPageLoader->load($request, $context);

        /** @var LandingPage|null $landingPage */
        if ($landingPage = $context->getContext()->getExtension('flLandingPage')) {
            return $this->redirect($landingPage->getLink(), 301);
        }

        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }

    private function handleFindologicSearchParams(Request $request): ?Response
    {
        if ($uri = $this->filterHandler->handleFindologicSearchParams($request)) {
            return $this->redirect($uri);
        }

        return null;
    }

    /**
     * @HttpCache()
     * @RouteScope(scopes={"storefront"})
     * @Route("/suggest", name="frontend.search.suggest", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        return $this->decorated->suggest($context, $request);
    }

    /**
     * @HttpCache()
     * Route to load the listing filters
     * @RouteScope(scopes={"storefront"})
     * @Route("/widgets/search/{search}", name="widgets.search.pagelet", methods={"GET", "POST"},
     *     defaults={"XmlHttpRequest"=true})
     *
     * @throws MissingRequestParameterException
     */
    public function pagelet(Request $request, SalesChannelContext $context): Response
    {
        return $this->decorated->pagelet($request, $context);
    }

    /**
     * @HttpCache()
     * Route to load the listing filters
     * @RouteScope(scopes={"storefront"})
     * @Route(
     *      "/widgets/search",
     *      name="widgets.search.pagelet.v2",
     *      methods={"GET", "POST"},
     *      defaults={"XmlHttpRequest"=true}
     * )
     *
     * @throws MissingRequestParameterException
     */
    public function ajax(Request $request, SalesChannelContext $context): Response
    {
        return $this->decorated->ajax($request, $context);
    }

    /**
     * @HttpCache()
     * Route to load the available listing filters
     * @RouteScope(scopes={"storefront"})
     * @Route("/widgets/search/filter", name="widgets.search.filter", methods={"GET", "POST"},
     *     defaults={"XmlHttpRequest"=true})
     */
    public function filter(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $this->config->initializeBySalesChannel($salesChannelContext);
        if (
            !Utils::shouldHandleRequest(
                $request,
                $salesChannelContext->getContext(),
                $this->serviceConfigResource,
                $this->config
            )
        ) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        $event = new ProductSearchCriteriaEvent($request, new Criteria(), $salesChannelContext);
        $this->findologicSearchService->doFilter($event);

        $result = $this->filterHandler->handleAvailableFilters($event);
        if (!$event->getCriteria()->hasExtension('flAvailableFilters')) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        return new JsonResponse($result);
    }
}
