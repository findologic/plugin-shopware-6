<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Export\Responses\PreconditionFailedResponse;
use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\FinSearch\Validators\ExportConfigurationBase;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    private LoggerInterface $logger;

    private RouterInterface $router;

    private HeaderHandler $headerHandler;

    private CacheItemPoolInterface $cache;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepository $customerGroupRepository;

    private EntityRepository $categoryRepository;

    private EntityRepository $pluginRepository;

    private EntityRepository $productRepository;

    private ProductStreamBuilder $productStreamBuilder;

    private ?SalesChannelContext $salesChannelContext;

    private ExportConfigurationBase $exportConfig;

    private ExportContext $exportContext;

    private ProductService $productService;

    private ProductSearcher $productSearcher;

    /** @var Export|XmlExport|ProductIdExport */
    private $export;

    private ?SalesChannelService $salesChannelService;

    private Config $pluginConfig;

    private DynamicProductGroupService $dynamicProductGroupService;

    public function __construct(
        LoggerInterface $logger,
        RouterInterface $router,
        HeaderHandler $headerHandler,
        CacheItemPoolInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        EntityRepository $customerGroupRepository,
        EntityRepository $categoryRepository,
        EntityRepository $pluginRepository,
        EntityRepository $productRepository,
        ProductStreamBuilder $productStreamBuilder
    ) {
        $this->logger = $logger;
        $this->router = $router;
        $this->headerHandler = $headerHandler;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->categoryRepository = $categoryRepository;
        $this->pluginRepository = $pluginRepository;
        $this->productRepository = $productRepository;
        $this->productStreamBuilder = $productStreamBuilder;
    }

    /**
     * @Route("/findologic", name="frontend.findologic.export", options={"seo"="false"}, methods={"GET"})
     */
    public function export(Request $request, ?SalesChannelContext $context): Response
    {
        $this->initialize($request, $context);

        if ($errorResponse = $this->validate() ?? $this->validateDynamicGroupPrecondition($request)) {
            return $errorResponse;
        }

        return $this->legacyExtensionInstalled()
            ? $this->doLegacyExport()
            : $this->doExport();
    }

    /**
     * @Route("/findologic/dynamic-product-groups", name="frontend.findologic.export.dynamic_product_groups",
     *     options={"seo"="false"}, methods={"GET"})
     */
    public function exportProductGroup(Request $request, ?SalesChannelContext $context): Response
    {
        $this->initialize($request, $context);
        if ($errorResponse = $this->validate()) {
            return $errorResponse;
        }

        $total = $this->warmUpDynamicProductGroupsAndGetTotal();

        return new JsonResponse([
            'meta' => [
                'start' => $this->exportConfig->getStart(),
                'count' => $this->exportConfig->getCount(),
                'total' => $total
            ]
        ]);
    }

    protected function initialize(Request $request, ?SalesChannelContext $context): void
    {
        $this->exportConfig = ExportConfigurationBase::getInstance($request);

        $this->salesChannelService = $context ? $this->container->get(SalesChannelService::class) : null;
        $this->salesChannelContext = $this->salesChannelService ? $this->salesChannelService
            ->getSalesChannelContext($context, $this->exportConfig->getShopkey()) : null;
        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);

        $this->pluginConfig = $this->buildPluginConfig();

        $this->productService = ProductService::getInstance(
            $this->container,
            $this->salesChannelContext,
            $this->pluginConfig
        );

        $this->export = Export::getInstance(
            $this->exportConfig->getProductId() ? Export::TYPE_PRODUCT_ID : Export::TYPE_XML,
            $this->router,
            $this->container,
            $this->logger,
            $this->eventDispatcher,
            $this->pluginConfig->getCrossSellingCategories()
        );

        // No need to initialize components relying on the sales channel context.
        // Export will not continue anyway
        if (!$this->salesChannelContext) {
            return;
        }

        $this->exportContext = $this->buildExportContext();
        $this->container->set('fin_search.export_context', $this->exportContext);

        $this->productSearcher = $this->container->get(ProductSearcher::class);

        $this->dynamicProductGroupService = $this->getDynamicProductGroupService();
        $this->container->set('fin_search.dynamic_product_group', $this->dynamicProductGroupService);

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

    protected function validateDynamicGroupPrecondition(Request $request): ?Response
    {
        $excludeProductGroups = $request->query->getBoolean('excludeProductGroups');
        if (!$excludeProductGroups && !$this->dynamicProductGroupService->areDynamicProductGroupsCached()) {
            return new PreconditionFailedResponse();
        }

        return null;
    }

    protected function doExport(): Response
    {
        $products = $this->productSearcher->findVisibleProducts(
            $this->exportConfig->getCount(),
            $this->exportConfig->getStart(),
            $this->exportConfig->getProductId()
        );

        $items = $this->export->buildItems(
            $products->getElements()
        );

        return $this->export->buildResponse(
            $items,
            $this->exportConfig->getStart(),
            $this->productSearcher->findTotalProductCount(),
            $this->headerHandler->getHeaders()
        );
    }

    public function doLegacyExport(): Response
    {
        if ($this->exportConfig->getStart() === 0) {
            $this->logger->info(
                sprintf(
                    '%s %s %s %s',
                    'Decorating the FindologicProduct or ProductService class is deprecated since 3.x',
                    'and will be removed in 5.0! Consider decorating the responsible export adapters in',
                    'FinSearch/Export/Adapters or the relevant services in FinSearch/Export/Search.',
                    'Make sure to follow the upgrade guide at FinSearch/UPGRADE-3.0.'
                )
            );
        }

        $products = $this->productService->searchVisibleProducts(
            $this->exportConfig->getCount(),
            $this->exportConfig->getStart(),
            $this->exportConfig->getProductId()
        );

        $items = $this->export->buildItemsLegacy(
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

    protected function warmUpDynamicProductGroupsAndGetTotal(): int
    {
        if ($this->exportConfig->getStart() === 0) {
            $this->dynamicProductGroupService->clearGeneralCache();
        }

        if (!$this->dynamicProductGroupService->areDynamicProductGroupsCached()) {
            $this->dynamicProductGroupService->warmUp();
        }

        return $this->dynamicProductGroupService->getDynamicProductGroupsTotal();
    }

    private function validateExportConfiguration(): array
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($this->exportConfig);

        $messages = [];
        if ($violations->count() > 0) {
            $messages = array_map(static function (ConstraintViolation $violation) {
                return sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }, current((array_values((array)$violations))));
        }

        return $messages;
    }

    private function buildPluginConfig(): Config
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
        return new DynamicProductGroupService(
            $this->productRepository,
            $this->categoryRepository,
            $this->productStreamBuilder,
            $this->salesChannelContext,
            $this->exportConfig,
            $this->cache,
        );
    }

    private function buildExportContext(): ExportContext
    {
        return new ExportContext(
            $this->exportConfig->getShopkey(),
            $this->getAllCustomerGroups(),
            Utils::fetchNavigationCategoryFromSalesChannel(
                $this->categoryRepository,
                $this->salesChannelContext->getSalesChannel()
            )
        );
    }

    private function getAllCustomerGroups(): array
    {
        return $this->customerGroupRepository
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();
    }

    private function legacyExtensionInstalled(): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'ExtendFinSearch'));

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepository
            ->search($criteria, $this->salesChannelContext->getContext())
            ->first();
        if ($plugin !== null && $plugin->getActive()) {
            return version_compare($plugin->getVersion(), '3.0.0', '<');
        }

        return false;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    public function getHeaderHandler(): HeaderHandler
    {
        return $this->headerHandler;
    }

    public function getCache(): CacheItemPoolInterface
    {
        return $this->cache;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function getCustomerGroupRepository(): EntityRepository
    {
        return $this->customerGroupRepository;
    }

    public function getSalesChannelContext(): ?SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getExportConfig(): ExportConfigurationBase
    {
        return $this->exportConfig;
    }

    public function getExportContext(): ExportContext
    {
        return $this->exportContext;
    }

    public function getProductSearcher(): ProductSearcher
    {
        return $this->productSearcher;
    }

    public function getExport(): Export
    {
        return $this->export;
    }

    public function getPluginConfig(): Config
    {
        return $this->pluginConfig;
    }
}
