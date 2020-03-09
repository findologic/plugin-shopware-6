<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Storefront\Controller;

use FINDOLOGIC\FinSearch\Storefront\Page\Search\SearchPageLoader;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\SearchController as ShopwareSearchController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
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

    public function __construct(SearchPageLoader $searchPageLoader, SuggestPageLoader $suggestPageLoader)
    {
        parent::__construct($searchPageLoader, $suggestPageLoader);

        $this->searchPageLoader = $searchPageLoader;
        $this->suggestPageLoader = $suggestPageLoader;
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
        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }

    private function handleFindologicSearchParams(Request $request): ?Response
    {
        $queryParams = $request->query->all();
        $mappedParams = [];

        $attributes = $request->get('attrib');
        if ($attributes) {
            foreach ($attributes as $key => $attribute) {
                foreach ($attribute as $value) {
                    $mappedParams[$key] = $value;
                }
            }

            unset($queryParams['attrib']);
        }

        $catFilter = $request->get('catFilter');
        if ($catFilter) {
            if (!empty($catFilter)) {
                if (is_array($catFilter)) {
                    $catFilter = end($catFilter);
                }
                $mappedParams['cat'] = $catFilter;
            }

            unset($queryParams['catFilter']);
        }

        if ($mappedParams === []) {
            return null;
        }

        $params = array_merge($queryParams, $mappedParams);
        return $this->redirect(sprintf('%s?%s', $request->getBasePath(), http_build_query($params, '', '&', PHP_QUERY_RFC3986)));
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
}
