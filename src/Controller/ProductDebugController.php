<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductDebugService;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Validators\DebugExportConfiguration;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ProductDebugController extends ExportController
{
    /** @var DebugExportConfiguration */
    protected $exportConfig;

    /** @var ProductDebugService */
    protected $productService;

    /**
     * @Route("/findologic/debug", name="frontend.findologic.debug", options={"seo"="false"}, methods={"GET"})
     */
    public function export(Request $request, ?SalesChannelContext $context): Response
    {
        return parent::export($request, $context);
    }

    /**
     * @param Request $request
     * @param SalesChannelContext|null $context
     */
    protected function initialize(Request $request, ?SalesChannelContext $context): void
    {
        parent::initialize($request, $context);

        $this->exportConfig = DebugExportConfiguration::getInstance($request);
    }

    protected function getProductServiceInstance(): ProductService
    {
        return ProductDebugService::getInstance(
            $this->container,
            $this->salesChannelContext,
            $this->pluginConfig
        );
    }

    protected function getExportInstance(): Export
    {
        return Export::getInstance(
            Export::TYPE_PRODUCT_ID,
            $this->router,
            $this->container,
            $this->logger,
            $this->pluginConfig->getCrossSellingCategories()
        );
    }

    protected function doExport(): Response
    {
        $this->warmUpDynamicProductGroups();

        $this->export->buildItems(
            [$this->productService->fetchProduct($this->exportConfig->getProductId())],
            $this->exportConfig->getShopkey(),
            $this->productService->getAllCustomerGroups()
        );

        return new JsonResponse(
            $this->productService->getDebugInformation(
                $this->exportConfig->getProductId(),
                $this->exportConfig->getShopkey(),
                $this->export->getErrorHandler()->getExportErrors()
            )
        );
    }
}
