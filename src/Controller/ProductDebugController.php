<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Debug\ProductDebugSearcher;
use FINDOLOGIC\FinSearch\Export\Debug\ProductDebugService;
use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\ProductServiceSeparateVariants;
use FINDOLOGIC\FinSearch\Validators\DebugExportConfiguration;
use FINDOLOGIC\FinSearch\Validators\ExportConfigurationBase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ProductDebugController extends ExportController
{
    /** @var ProductDebugSearcher */
    private $productDebugSearcher;

    /** @var ProductDebugService */
    private $productDebugService;

    /**
     * @Route("/findologic/debug", name="frontend.findologic.debug", options={"seo"="false"}, methods={"GET"})
     */
    public function export(Request $request, ?SalesChannelContext $context): Response
    {
        return parent::export($request, $context);
    }

    protected function initialize(Request $request, ?SalesChannelContext $context): void
    {
        parent::initialize($request, $context);

        $this->productDebugSearcher = $this->container->get(ProductDebugSearcher::class);
        $this->productDebugService = $this->container->get(ProductDebugService::class);
    }

    protected function getExportInstance(): Export
    {
        return Export::getInstance(
            Export::TYPE_PRODUCT_ID,
            $this->getRouter(),
            $this->container,
            $this->getLogger(),
            $this->getEventDispatcher(),
            $this->getPluginConfig()->getCrossSellingCategories()
        );
    }

    protected function getExportConfigInstance(Request $request): ExportConfigurationBase
    {
        return DebugExportConfiguration::getInstance($request);
    }

    protected function doExport(): Response
    {
        $this->warmUpDynamicProductGroups();

        $mainProduct = $this->productDebugSearcher->getMainProductById($this->getExportConfig()->getProductId());
        $product = $this->productDebugSearcher->findVisibleProducts(
            null,
            null,
            $mainProduct->getId()
        )->first();

        /** @var XMLItem[] $xmlProducts */
        $xmlProducts = $this->getExport()->buildItems($product ? [$product] : []);

        return $this->productDebugService->getDebugInformation(
            $this->getExportConfig()->getProductId(),
            $this->getExportConfig()->getShopkey(),
            count($xmlProducts) ? $xmlProducts[0] : null,
            $product,
            $this->getExport()->getErrorHandler()->getExportErrors(),
        );
    }
}
