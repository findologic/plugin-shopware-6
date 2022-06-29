<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Debug\ProductDebugService;
use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductServiceSeparateVariants;
use FINDOLOGIC\FinSearch\Validators\DebugExportConfiguration;
use FINDOLOGIC\FinSearch\Validators\ExportConfigurationBase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ProductDebugController extends ExportController
{
    /** @var ProductDebugService */
    private $productDebugService;

    /**
     * @param SalesChannelContextFactory|AbstractSalesChannelContextFactory $salesChannelContextFactory
     */
    public function __construct(
        LoggerInterface $logger,
        RouterInterface $router,
        HeaderHandler $headerHandler,
        $salesChannelContextFactory,
        CacheItemPoolInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        EntityRepository $customerGroupRepository,
        ProductDebugService $productDebugService
    ) {
        parent::__construct($logger, $router, $headerHandler, $salesChannelContextFactory, $cache, $eventDispatcher, $customerGroupRepository);

        $this->productDebugService = $productDebugService;
    }

    /**
     * @Route("/findologic/debug", name="frontend.findologic.debug", options={"seo"="false"}, methods={"GET"})
     */
    public function export(Request $request, ?SalesChannelContext $context): Response
    {
        return parent::export($request, $context);
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

//        $mainProduct = $this->getProductSearcher()->getMainProductById($this->getExportConfig()->getProductId());
        $product = $this->getProductSearcher()->findVisibleProducts(
            null,
            null,
            $this->getExportConfig()->getProductId()
        )->first();

        /** @var XMLItem[] $xmlProducts */
        $xmlProducts = $this->getExport()->buildItems($product ? [$product] : []);

        return $this->productDebugService->getDebugInformation(
            $this->getExportConfig()->getProductId(),
            $this->getExportConfig()->getShopkey(),
            count($xmlProducts) ? $xmlProducts[0] : null,
            $product,
            $this->getExport()->getErrorHandler()->getExportErrors(),
            $this->getProductSearcher()
        );
    }
}
