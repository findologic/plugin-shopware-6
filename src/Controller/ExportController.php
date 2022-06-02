<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\ExportItemAdapter;
use FINDOLOGIC\FinSearch\Export\ExportItemAdapterInterface;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\ProductServiceSeparateVariants;
use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ExportController extends AbstractController
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Router */
    private $router;

    /** @var HeaderHandler */
    private $headerHandler;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ExportConfiguration */
    private $exportConfig;

    /** @var ExportContext */
    private $exportContext;

    /** @var ExportItemAdapterInterface */
    private $exportItemAdapter;

    /** @var ProductServiceSeparateVariants */
    private $productService;

    /** @var Config */
    private $pluginConfig;

    /** @var Export|XmlExport|ProductIdExport */
    private $export;

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var SalesChannelService|null */
    private $salesChannelService;

    /** @var ?DynamicProductGroupService */
    private $dynamicProductGroupService;

    public function __construct(
        LoggerInterface $logger,
        RouterInterface $router,
        HeaderHandler $headerHandler,
        $salesChannelContextFactory,
        CacheItemPoolInterface $cache
    ) {
        $this->logger = $logger;
        $this->router = $router;
        $this->headerHandler = $headerHandler;
        $this->cache = $cache;
    }

    /**
     * @Route("/findologic", name="frontend.findologic.export", options={"seo"="false"}, methods={"GET"})
     */
    public function export(Request $request, ?SalesChannelContext $context): Response
    {
        $this->initialize($request, $context);
        if ($errorResponse = $this->validate()) {
            return $errorResponse;
        }

        return $this->doExport();
    }

    /**
     * @param Request $request
     * @param SalesChannelContext|null $context
     */
    protected function initialize(Request $request, ?SalesChannelContext $context): void
    {
        $this->exportConfig = ExportConfiguration::getInstance($request);
        $this->salesChannelService = $context ? $this->container->get(SalesChannelService::class) : null;
        $this->salesChannelContext = $this->salesChannelService ? $this->salesChannelService
            ->getSalesChannelContext($context, $this->exportConfig->getShopkey()) : null;

        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->pluginConfig = $this->getPluginConfig();

        /** @var ProductServiceSeparateVariants productService */
        $this->productService = ProductServiceSeparateVariants::getInstance(
            $this->container,
            $this->salesChannelContext,
            $this->pluginConfig
        );

        $this->exportContext = $this->buildExportContext();
        $this->container->set('fin_search.export_context', $this->exportContext);
        $this->dynamicProductGroupService = $this->getDynamicProductGroupServiceInstance();

        $this->exportItemAdapter = $this->container->get(ExportItemAdapter::class);

        $this->export = Export::getInstance(
            $this->exportConfig->getProductId() ? Export::TYPE_PRODUCT_ID : Export::TYPE_XML,
            $this->router,
            $this->container,
            $this->logger,
            $this->exportItemAdapter,
            $this->productService,
            $this->pluginConfig->getCrossSellingCategories()
        );

        $this->manipulateRequestWithSalesChannelInformation($request);
    }

    protected function validate(): ?Response
    {
        $messages = $this->validateStateAndGetErrorMessages();
        if (count($messages) > 0) {
            $errorHandler = new ProductErrorHandler();
            $errorHandler->getExportErrors()->addGeneralErrors($messages);

            return $this->export->buildErrorResponse($errorHandler, $this->headerHandler->getHeaders());
        }

        return null;
    }

    protected function doExport(): Response
    {
        $this->warmUpDynamicProductGroups();

        $products = $this->productService->searchVisibleProducts(
            $this->exportConfig->getCount(),
            $this->exportConfig->getStart(),
            $this->exportConfig->getProductId()
        );

        $exportContext = $this->buildExportContext();
        $this->container->set('fin_search.export_context', $exportContext);

        $items = $this->export->buildItems(
            $products->getElements(),
            $this->exportConfig->getShopkey(),
            $exportContext->getCustomerGroups()
        );

        return $this->export->buildResponse(
            $items,
            $this->exportConfig->getStart(),
            $this->productService->getTotalProductCount(),
            $this->headerHandler->getHeaders()
        );
    }

    protected function validateStateAndGetErrorMessages(): array
    {
        $messages = $this->validateExportConfiguration($this->exportConfig);
        if (count($messages) > 0) {
            return $messages;
        }

        if ($this->salesChannelContext === null) {
            $messages[] = sprintf(
                'Shopkey %s is not assigned to any sales channel.',
                $this->exportConfig->getShopkey()
            );
        }

        return $messages;
    }

    protected function getDynamicProductGroupServiceInstance(): ?DynamicProductGroupService
    {
        if (Utils::versionLowerThan('6.3.1.0', $this->container->getParameter('kernel.shopware_version'))) {
            return null;
        }

        $dynamicProductGroupService = DynamicProductGroupService::getInstance(
            $this->container,
            $this->cache,
            $this->salesChannelContext->getContext(),
            $this->exportConfig->getShopkey(),
            $this->exportConfig->getStart()
        );

        $dynamicProductGroupService->setSalesChannel($this->salesChannelContext->getSalesChannel());

        return $dynamicProductGroupService;
    }

    protected function warmUpDynamicProductGroups(): void
    {
        if (Utils::versionLowerThan('6.3.1.0', $this->container->getParameter('kernel.shopware_version'))) {
            return;
        }

        $dynamicProductGroupService = DynamicProductGroupService::getInstance(
            $this->container,
            $this->cache,
            $this->salesChannelContext->getContext(),
            $this->exportConfig->getShopkey(),
            $this->exportConfig->getStart()
        );

        $dynamicProductGroupService->setSalesChannel($this->salesChannelContext->getSalesChannel());
        if (!$dynamicProductGroupService->isWarmedUp()) {
            $dynamicProductGroupService->warmUp();
        }
    }

    private function validateExportConfiguration(ExportConfiguration $config): array
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($config);

        $messages = [];
        if ($violations->count() > 0) {
            $messages = array_map(function (ConstraintViolation $violation) {
                return sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }, current((array_values((array)$violations))));
        }

        return $messages;
    }

    private function getPluginConfig(): Config
    {
        /** @var Config $config */
        $config = $this->container->get(Config::class);
        if ($this->salesChannelContext) {
            $config->initializeBySalesChannel($this->salesChannelContext);
        }

        return $config;
    }

    private function manipulateRequestWithSalesChannelInformation(Request $originalRequest): void
    {
        // There is no need to manipulate anything, if there is no SalesChannelContext.
        if (!$this->salesChannelContext) {
            return;
        }

        $request = $this->salesChannelService->getRequest($originalRequest, $this->salesChannelContext);
        $attributes = $request->attributes->all();

        $originalRequest->attributes->replace($attributes);
    }

    private function buildExportContext(): ExportContext
    {
        return new ExportContext(
            $this->exportConfig->getShopkey(),
            $this->productService->getAllCustomerGroups(),
            Utils::fetchNavigationCategoryFromSalesChannel(
                $this->container->get('category.repository'),
                $this->salesChannelContext->getSalesChannel()
            )
        );
    }
}
