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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\SearchController as ShopwareSearchController;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class SearchController extends ShopwareSearchController
{
    private SearchPageLoader $searchPageLoader;

    private Config $config;

    public function __construct(
        private readonly ShopwareSearchController $decorated,
        private readonly FilterHandler $filterHandler,
        private readonly FindologicSearchService $findologicSearchService,
        private readonly ServiceConfigResource $serviceConfigResource,
        ?SearchPageLoader $searchPageLoader,
        ContainerInterface $container,
        FindologicConfigService $findologicConfigService
    ) {
        $this->container = $container;
        $this->searchPageLoader = $this->buildSearchPageLoader($searchPageLoader);
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    private function buildSearchPageLoader(?SearchPageLoader $searchPageLoader): SearchPageLoader
    {
        if (!$searchPageLoader) {
            return $this->container->get(FindologicSearchPageLoader::class);
        }

        return $searchPageLoader;
    }

    #[Route(
        path: '/search',
        name: 'frontend.search.page',
        defaults: [
            '_httpCache' => true,
        ],
        methods: ['GET'],
    )]
    public function search(SalesChannelContext $context, Request $request): Response
    {
        $this->config->initializeBySalesChannel($context);
        if (
            !Utils::shouldHandleRequest(
                $request,
                $context->getContext(),
                $this->serviceConfigResource,
                $this->config
            )
        ) {
            return $this->decorated->search($context, $request);
        }

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

    #[Route(
        path: '/suggest',
        name: 'frontend.search.suggest',
        defaults: [
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET'],
    )]
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        return $this->decorated->suggest($context, $request);
    }

    #[Route(
        path: '/widgets/search',
        name: 'widgets.search.pagelet.v2',
        defaults: [
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET', 'POST'],
    )]
    public function ajax(Request $request, SalesChannelContext $context): Response
    {
        return $this->decorated->ajax($request, $context);
    }

    #[Route(
        path: '/widgets/search/filter',
        name: 'widgets.search.filter',
        defaults: [
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET', 'POST'],
    )]
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

        $criteria = new Criteria();
        $this->findologicSearchService->doFilter($request, $criteria, $salesChannelContext);

        $result = $this->filterHandler->handleAvailableFilters($criteria);
        if (!$criteria->hasExtension('flAvailableFilters')) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        return new JsonResponse($result);
    }
}
