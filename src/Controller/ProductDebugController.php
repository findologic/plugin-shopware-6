<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Search\ProductDebugSearcher;
use FINDOLOGIC\Shopware6Common\Export\Services\ProductDebugService;
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
    private ProductDebugSearcher $productDebugSearcher;

    private ProductDebugService $productDebugService;

    /**
     * @Route("/findologic/debug", name="frontend.findologic.debug", options={"seo"="false"}, methods={"GET"})
     */
    public function export(Request $request, ?SalesChannelContext $context): Response
    {
        return parent::export($request, $context);
    }

    protected function postInitialize(Request $request): void
    {
        parent::postInitialize($request);

        $this->productDebugSearcher = $this->container->get(ProductDebugSearcher::class);
        $this->productDebugService = $this->container->get(ProductDebugService::class);
    }

    protected function doExport(): Response
    {
        $mainProduct = $this->productDebugSearcher->getMainProductById($this->exportConfig->getProductId());
        $product = $this->productDebugSearcher->findVisibleProducts(
            null,
            null,
            $mainProduct ? $mainProduct->id : $this->exportConfig->getProductId()
        )->first();

        /** @var XMLItem[] $xmlProducts */
        $xmlProducts = $this->export->buildItems($product ? [$product] : []);

        return $this->productDebugService->getDebugInformation(
            $this->exportConfig->getProductId(),
            $this->exportConfig->getShopkey(),
            count($xmlProducts) ? $xmlProducts[0] : null,
            $product,
            $this->export->getErrorHandler()->getExportErrors()
        );
    }
}
