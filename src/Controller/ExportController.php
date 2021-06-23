<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;

use function filter_var;

use const FILTER_VALIDATE_BOOLEAN;

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

    /** @var SalesChannelContextFactory|AbstractSalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ExportConfiguration */
    private $exportConfig;

    /** @var ProductService */
    private $productService;

    /** @var Config */
    private $pluginConfig;

    /** @var Export|XmlExport|ProductIdExport */
    private $export;

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var SalesChannelService|null */
    private $salesChannelService;

    /** @var int */
    private $total = 0;

    /**
     * @param SalesChannelContextFactory|AbstractSalesChannelContextFactory $salesChannelContextFactory
     */
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
        $this->salesChannelContextFactory = $salesChannelContextFactory;
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

        $excludeProductGroups = filter_var($request->get('excludeProductGroups'), FILTER_VALIDATE_BOOLEAN);
        $dynamicProductGroupService = $this->getDynamicProductGroupService();
        if (!$excludeProductGroups && !$dynamicProductGroupService->isDynamicProductGroupWarmedUp()) {
            $errorMessage = 'Dynamic Product Groups have not been warmed up yet. ';
            $errorMessage .= 'This may cause missing categories! ';
            $errorMessage .= "Warm them up by calling the route '/findologic/dynamic-product-groups', ";
            $errorMessage .= 'or disable fetching of Dynamic Product Groups by adding the query parameter ';
            $errorMessage .= "'excludeProductGroups=true'";

            return new JsonResponse(['error' => $errorMessage], Response::HTTP_PRECONDITION_REQUIRED);
        }

        return $this->doExport();
    }

    /**
     * @Route("/findologic/dynamic-product-groups", name="frontend.findologic.export.productgroup",
     *     options={"seo"="false"}, methods={"GET"})
     */
    public function exportProductGroup(Request $request, ?SalesChannelContext $context): Response
    {
        $this->initialize($request, $context);
        if ($errorResponse = $this->validate()) {
            return $errorResponse;
        }

        $this->warmUpDynamicProductGroups();

        return new JsonResponse([
            'meta' => [
                'start' => $this->exportConfig->getStart(),
                'count' => $this->exportConfig->getCount(),
                'total' => $this->total
            ]
        ]);
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

        $this->productService = ProductService::getInstance($this->container, $this->salesChannelContext);
        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->pluginConfig = $this->getPluginConfig();

        $this->export = Export::getInstance(
            $this->exportConfig->getProductId() ? Export::TYPE_PRODUCT_ID : Export::TYPE_XML,
            $this->router,
            $this->container,
            $this->logger,
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
        $products = $this->productService->searchVisibleProducts(
            $this->exportConfig->getCount(),
            $this->exportConfig->getStart(),
            $this->exportConfig->getProductId()
        );

        $items = $this->export->buildItems(
            $products->getElements(),
            $this->exportConfig->getShopkey(),
            $this->productService->getAllCustomerGroups()
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

    protected function warmUpDynamicProductGroups(): void
    {
        if (Utils::versionLowerThan('6.3.1.0')) {
            return;
        }

        $dynamicProductGroupService = $this->getDynamicProductGroupService();
        if (!$dynamicProductGroupService->isWarmedUp()) {
            $dynamicProductGroupService->warmUp();
        }

        $this->total = $dynamicProductGroupService->getDynamicProductGroupTotalFromCache();
        $dynamicProductGroupService->setDynamicProductGroupWarmedUpFlag($this->total);
    }

    private function validateExportConfiguration(ExportConfiguration $config): array
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($config);

        $messages = [];
        if ($violations->count() > 0) {
            $messages = array_map(static function (ConstraintViolation $violation) {
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

    protected function getDynamicProductGroupService(): DynamicProductGroupService
    {
        $dynamicProductGroupService = DynamicProductGroupService::getInstance(
            $this->container,
            $this->cache,
            $this->salesChannelContext->getContext(),
            $this->exportConfig->getShopkey(),
            $this->exportConfig->getStart(),
            $this->exportConfig->getCount()
        );

        $dynamicProductGroupService->setSalesChannel($this->salesChannelContext->getSalesChannel());

        return $dynamicProductGroupService;
    }
}
