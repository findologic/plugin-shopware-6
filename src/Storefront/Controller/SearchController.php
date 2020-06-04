<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Storefront\Controller;

use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Struct\LandingPage;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\SearchController as ShopwareSearchController;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends ShopwareSearchController
{
    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    /**
     * @var SuggestPageLoader
     */
    private $suggestPageLoader;

    /**
     * @var FilterHandler
     */
    private $filterHandler;

    public function __construct(
        SearchPageLoader $searchPageLoader,
        SuggestPageLoader $suggestPageLoader,
        ?FilterHandler $filterHandler = null
    ) {
        parent::__construct($searchPageLoader, $suggestPageLoader);

        $this->searchPageLoader = $searchPageLoader;
        $this->suggestPageLoader = $suggestPageLoader;
        $this->filterHandler = $filterHandler ?? new FilterHandler();
    }

    /**
     * @HttpCache()
     * @RouteScope(scopes={"storefront"})
     * @Route("/search", name="frontend.search.page", methods={"GET"})
     */
    public function search(SalesChannelContext $context, Request $request): Response
    {
        if ($response = $this->handleFindologicSearchParams($request)) {
            return $response;
        }

        $page = $this->searchPageLoader->load($request, $context);

        /** @var LandingPage|null $landingPage */
        if ($landingPage = $context->getContext()->getExtension('flLandingPage')) {
            return $this->redirect($landingPage->getLink(), 301);
        }

        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }

    /**
     * @HttpCache()
     * @RouteScope(scopes={"storefront"})
     * @Route("/suggest", name="frontend.search.suggest", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->suggestPageLoader->load($request, $context);

        return $this->renderStorefront(
            '@Storefront/storefront/layout/header/search-suggest.html.twig',
            ['page' => $page]
        );
    }

    /**
     * @HttpCache()
     *
     * Route to load the listing filters
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/widgets/search/{search}", name="widgets.search.pagelet", methods={"GET", "POST"},
     *     defaults={"XmlHttpRequest"=true})
     *
     * @throws MissingRequestParameterException
     */
    public function pagelet(Request $request, SalesChannelContext $context): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->searchPageLoader->load($request, $context);

        return $this->renderStorefront(
            '@Storefront/storefront/page/search/search-pagelet.html.twig',
            ['page' => $page]
        );
    }

    private function handleFindologicSearchParams(Request $request): ?Response
    {
        if ($uri = $this->filterHandler->handleFindologicSearchParams($request)) {
            return $this->redirect($uri);
        }

        return null;
    }
}
