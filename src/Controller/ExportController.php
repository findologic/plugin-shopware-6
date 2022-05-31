<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\ProductServiceSeparateVariants;
use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use FINDOLOGIC\FinSearch\Validators\ExportConfigurationBase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ExportController extends AbstractController
{
    /** @var LoggerInterface */
    private $logger;

    /** @var Router */
    private $router;

    /** @var HeaderHandler */
    private $headerHandler;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ExportConfigurationBase */
    private $exportConfig;

    /** @var ExportContext */
    private $exportContext;

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

    public function __construct(
        LoggerInterface $logger,
        RouterInterface $router,
        HeaderHandler $headerHandler,
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
        $this->initializePreValidation($request, $context);
        if ($errorResponse = $this->validate()) {
            return $errorResponse;
        }
        $this->initializePostValidation($request);

        return $this->doExport();
    }

    /**
     * @param Request $request
     * @param SalesChannelContext|null $context
     */
    protected function initializePreValidation(Request $request, ?SalesChannelContext $context): void
    {
        $this->exportConfig = $this->getExportConfigInstance($request);

        $this->salesChannelService = $context ? $this->container->get(SalesChannelService::class) : null;
        $this->salesChannelContext = $this->salesChannelService ? $this->salesChannelService
            ->getSalesChannelContext($context, $this->exportConfig->getShopkey()) : null;
        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);

        $this->pluginConfig = $this->initializePluginConfig();
        $this->export = $this->getExportInstance();
    }

    /**
     * @param Request $request
     */
    protected function initializePostValidation(Request $request): void
    {
        $this->productService = $this->getProductServiceInstance();

        $this->exportContext = $this->buildExportContext();
        $this->container->set('fin_search.export_context', $this->exportContext);

        $this->manipulateRequestWithSalesChannelInformation($request);
    }

    protected function getProductServiceInstance(): ProductServiceSeparateVariants
    {
        return ProductServiceSeparateVariants::getInstance(
            $this->container,
            $this->salesChannelContext,
            $this->pluginConfig
        );
    }

    protected function getExportInstance(): Export
    {
        return Export::getInstance(
            $this->exportConfig->getProductId() ? Export::TYPE_PRODUCT_ID : Export::TYPE_XML,
            $this->router,
            $this->container,
            $this->logger,
            $this->pluginConfig->getCrossSellingCategories()
        );
    }

    protected function getExportConfigInstance(Request $request): ExportConfigurationBase
    {
        return ExportConfiguration::getInstance($request);
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

    protected function validateStateAndGetErrorMessages(): array
    {
        $messages = $this->validateExportConfiguration();
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

    private function validateExportConfiguration(): array
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($this->exportConfig);

        $messages = [];
        if ($violations->count() > 0) {
            $messages = array_map(function (ConstraintViolation $violation) {
                return sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }, current((array_values((array)$violations))));
        }

        return $messages;
    }

    protected function doExport(): Response
    {
        $this->warmUpDynamicProductGroups();

        $products = $this->productService->searchVisibleProducts(
            $this->exportConfig->getCount(),
            $this->exportConfig->getStart(),
            $this->exportConfig->getProductId()
        );

        $items = $this->export->buildItems(
            $products->getElements(),
            $this->exportConfig->getShopkey(),
            $this->exportContext->getCustomerGroups()
        );

        return $this->export->buildResponse(
            $items,
            $this->exportConfig->getStart(),
            $this->productService->getTotalProductCount(),
            $this->headerHandler->getHeaders()
        );
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

    private function initializePluginConfig(): Config
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

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * @return SalesChannelContext
     */
    public function getSalesChannelContext(): ?SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    /**
     * @return ExportConfigurationBase
     */
    public function getExportConfig(): ExportConfigurationBase
    {
        return $this->exportConfig;
    }

    /**
     * @param ExportConfigurationBase $exportConfig
     */
    public function setExportConfig(ExportConfigurationBase $exportConfig): void
    {
        $this->exportConfig = $exportConfig;
    }

    /**
     * @return ExportContext
     */
    public function getExportContext(): ExportContext
    {
        return $this->exportContext;
    }

    /**
     * @return ProductServiceSeparateVariants
     */
    public function getProductService(): ProductServiceSeparateVariants
    {
        return $this->productService;
    }

    /**
     * @return Config
     */
    public function getPluginConfig(): Config
    {
        return $this->pluginConfig;
    }

    /**
     * @return Export|ProductIdExport|XmlExport
     */
    public function getExport()
    {
        return $this->export;
    }
}
