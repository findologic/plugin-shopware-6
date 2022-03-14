<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Storefront\Controller;

use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CmsController as ShopwareCmsController;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CmsController extends StorefrontController
{
    /** @var ShopwareCmsController */
    private $decorated;

    /** @var FilterHandler */
    private $filterHandler;

    /** @var FindologicSearchService */
    private $findologicSearchService;

    public function __construct(
        ShopwareCmsController $decorated,
        FilterHandler $filterHandler,
        ContainerInterface $container,
        FindologicSearchService $findologicSearchService
    ) {
        $this->container = $container;
        $this->decorated = $decorated;
        $this->filterHandler = $filterHandler;
        $this->findologicSearchService = $findologicSearchService;
    }

    /**
     * Route for cms data (used in XmlHttpRequest)
     *
     * @HttpCache()
     * @Route(
     *     "/widgets/cms/{id}",
     *     name="frontend.cms.page",
     *     methods={"GET", "POST"},
     *     defaults={"id"=null, "XmlHttpRequest"=true}
     * )
     *
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws PageNotFoundException
     */
    public function page(?string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return $this->decorated->page($id, $request, $salesChannelContext);
    }

    /**
     * Route to load a cms page which assigned to the provided navigation id.
     * Navigation id is required to load the slot config for the navigation
     *
     * @Route(
     *     "/widgets/cms/navigation/{navigationId}",
     *      name="frontend.cms.navigation.page",
     *      methods={"GET", "POST"},
     *      defaults={"navigationId"=null,"XmlHttpRequest"=true}
     * )
     *
     * @throws CategoryNotFoundException
     * @throws MissingRequestParameterException
     * @throws PageNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function category(
        ?string $navigationId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        return $this->decorated->category($navigationId, $request, $salesChannelContext);
    }

    /**
     * @HttpCache()
     *
     * Route to load the listing filters
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/widgets/cms/navigation/{navigationId}/filter", name="frontend.cms.navigation.filter",
     *      methods={"GET", "POST"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws MissingRequestParameterException
     */
    public function filter(string $navigationId, Request $request, SalesChannelContext $context): Response
    {
        if (!Utils::isFindologicEnabled($context)) {
            return $this->decorated->filter($navigationId, $request, $context);
        }

        $event = new ProductListingCriteriaEvent($request, new Criteria(), $context);
        $this->findologicSearchService->doFilter($event);

        $result = $this->filterHandler->handleAvailableFilters($event);
        if (!$event->getCriteria()->hasExtension('flAvailableFilters')) {
            return $this->decorated->filter($navigationId, $request, $context);
        }

        return new JsonResponse($result);
    }

    /**
     * @HttpCache()
     *
     * Route to load the cms element buy box product config which assigned to the provided product id.
     * Product id is required to load the slot config for the buy box
     *
     * @RouteScope(scopes={"storefront"})
     * @Route(
     *     "/widgets/cms/buybox/{productId}/switch",
     *     name="frontend.cms.buybox.switch",
     *     methods={"GET"},
     *     defaults={"productId"=null, "XmlHttpRequest"=true}
     * )
     *
     * @throws MissingRequestParameterException
     * @throws ProductNotFoundException
     */
    public function switchBuyBoxVariant(string $productId, Request $request, SalesChannelContext $context): Response
    {
        return $this->decorated->switchBuyBoxVariant($productId, $request, $context);
    }
}
