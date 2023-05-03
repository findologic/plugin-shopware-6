<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Storefront\Controller;

use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CmsController as ShopwareCmsController;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CmsController extends StorefrontController
{
    private Config $config;

    public function __construct(
        private readonly ShopwareCmsController $decorated,
        private readonly FilterHandler $filterHandler,
        private readonly FindologicSearchService $findologicSearchService,
        private readonly ServiceConfigResource $serviceConfigResource,
        ContainerInterface $container,
        FindologicConfigService $findologicConfigService
    ) {
        $this->container = $container;
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    /**
     * Route for cms data (used in XmlHttpRequest)
     *
     * @Route(
     *     "/widgets/cms/{id}",
     *     name="frontend.cms.page",
     *     methods={"GET", "POST"},
     *     defaults={
     *          "id"=null,
     *          "_routeScope"={"storefront"},
     *          "XmlHttpRequest"=true,
     *          "_httpCache"=true
     *     }
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
     *      defaults={
     *          "navigationId"=null,
     *          "_routeScope"={"storefront"},
     *          "XmlHttpRequest"=true
     *     }
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
     * Route to load the listing filters
     *
     * @Route(
     *     "/widgets/cms/navigation/{navigationId}/filter",
     *     name="frontend.cms.navigation.filter",
     *     methods={"GET", "POST"},
     *     defaults={
     *          "_routeScope"={"storefront"},
     *          "XmlHttpRequest"=true,
     *          "_httpCache"=true
     *     }
     * )
     *
     * @throws MissingRequestParameterException
     */
    public function filter(string $navigationId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $this->config->initializeBySalesChannel($salesChannelContext);
        if (
            !Utils::shouldHandleRequest(
                $request,
                $salesChannelContext->getContext(),
                $this->serviceConfigResource,
                $this->config,
                true
            )
        ) {
            return $this->decorated->filter($navigationId, $request, $salesChannelContext);
        }

        $event = new ProductListingCriteriaEvent($request, new Criteria(), $salesChannelContext);
        $this->findologicSearchService->doFilter($event);

        $result = $this->filterHandler->handleAvailableFilters($event);
        if (!$event->getCriteria()->hasExtension('flAvailableFilters')) {
            return $this->decorated->filter($navigationId, $request, $salesChannelContext);
        }

        return new JsonResponse($result);
    }

    /**
     * Route to load the cms element buy box product config which assigned to the provided product id.
     * Product id is required to load the slot config for the buy box
     *
     * @Route(
     *     "/widgets/cms/buybox/{productId}/switch",
     *     name="frontend.cms.buybox.switch",
     *     methods={"GET"},
     *     defaults={
     *          "productId"=null,
     *          "_routeScope"={"storefront"},
     *          "XmlHttpRequest"=true,
     *          "_httpCache"=true
     *     }
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
