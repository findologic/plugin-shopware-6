<?php

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Debug\DebugProductSearch;
use FINDOLOGIC\FinSearch\Export\Debug\ProductDebugService;
use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\ProductServiceSeparateVariants;
use FINDOLOGIC\FinSearch\Validators\DebugExportConfiguration;
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
    /** @var DebugProductSearch */
    private $debugProductSearch;

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

        $this->setExportConfig(DebugExportConfiguration::getInstance($request));
        $this->debugProductSearch = new DebugProductSearch($this->container, $this->getSalesChannelContext());
    }

    protected function getProductServiceInstance(): ProductServiceSeparateVariants
    {
        return ProductDebugService::getInstance(
            $this->container,
            $this->getSalesChannelContext(),
            $this->getPluginConfig()
        );
    }

    protected function getExportInstance(): Export
    {
        return Export::getInstance(
            Export::TYPE_PRODUCT_ID,
            $this->getRouter(),
            $this->container,
            $this->getLogger(),
            $this->getPluginConfig()->getCrossSellingCategories()
        );
    }

    protected function doExport(): Response
    {
        $this->warmUpDynamicProductGroups();

        $mainProduct = $this->debugProductSearch->getMainProductById($this->getExportConfig()->getProductId());
        $product = $this->getProductService()->searchVisibleProducts(
            null,
            null,
            $mainProduct->getId()
        )->first();

        /** @var XMLItem[] $xmlProducts */
        $xmlProducts = $this->getExport()->buildItems(
            $product ? [$product] : [],
            $this->getExportConfig()->getShopkey(),
            $this->getExportContext()->getCustomerGroups()
        );

        return $this->getProductService()->getDebugInformation(
            $this->getExportConfig()->getProductId(),
            $this->getExportConfig()->getShopkey(),
            count($xmlProducts) ? $xmlProducts[0] : null,
            $product,
            $this->getExport()->getErrorHandler()->getExportErrors(),
            $this->debugProductSearch
        );
    }

    /**
     * @return ProductDebugService
     */
    public function getProductService(): ProductServiceSeparateVariants
    {
        return parent::getProductService();
    }
}
