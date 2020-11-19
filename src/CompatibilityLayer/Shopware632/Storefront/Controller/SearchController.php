<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\CompatibilityLayer\Shopware632\Storefront\Controller;

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

class SearchController
{
    /** @var ShopwareSearchController */
    private $decorated;

    /** @var FilterHandler */
    private $filterHandler;

    public function __construct(
        ShopwareSearchController $decorated,
        FilterHandler $filterHandler
    ) {
        $this->decorated = $decorated;
        $this->filterHandler = $filterHandler;
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

        $response = $this->decorated->search($context, $request);

        /** @var LandingPage|null $landingPage */
        if ($landingPage = $context->getContext()->getExtension('flLandingPage')) {
            return $this->redirect($landingPage->getLink(), 301);
        }

        return $response;
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
        return $this->decorated->pagelet($request, $context);
    }

    /**
     * @HttpCache()
     *
     * Route to load the listing filters
     *
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

    private function handleFindologicSearchParams(Request $request): ?Response
    {
        if ($uri = $this->filterHandler->handleFindologicSearchParams($request)) {
            return $this->redirect($uri);
        }

        return null;
    }
}
