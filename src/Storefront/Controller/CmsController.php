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
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CmsController as ShopwareCmsController;
use Shopware\Storefront\Controller\StorefrontController;
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

    #[Route(
        path: '/widgets/cms/{id}',
        name: 'frontend.cms.page',
        defaults: [
            'id' => null,
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET', 'POST'],
    )]
    public function page(?string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return $this->decorated->page($id, $request, $salesChannelContext);
    }

    #[Route(
        path: '/widgets/cms/navigation/{navigationId}',
        name: 'frontend.cms.navigation.page',
        defaults: [
            'navigationId' => null,
            'XmlHttpRequest' => true,
        ],
        methods: ['GET', 'POST'],
    )]
    public function category(
        ?string $navigationId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        return $this->decorated->category($navigationId, $request, $salesChannelContext);
    }

    #[Route(
        path: '/widgets/cms/navigation/{navigationId}/filter',
        name: 'frontend.cms.navigation.filter',
        defaults: [
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET', 'POST'],
    )]
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

        $criteria = new Criteria();
        $this->findologicSearchService->doFilter($request, $criteria, $salesChannelContext);

        $result = $this->filterHandler->handleAvailableFilters($criteria);
        if (!$criteria->hasExtension('flAvailableFilters')) {
            return $this->decorated->filter($navigationId, $request, $salesChannelContext);
        }

        return new JsonResponse($result);
    }

    #[Route(
        path: '/widgets/cms/buybox/{productId}/switch',
        name: 'frontend.cms.buybox.switch',
        defaults: [
            'productId' => null,
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET'],
    )]
    public function switchBuyBoxVariant(string $productId, Request $request, SalesChannelContext $context): Response
    {
        return $this->decorated->switchBuyBoxVariant($productId, $request, $context);
    }
}
