<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Export\Adapters\ExportItemAdapter;
use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\Responses\PreconditionFailedResponse;
use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\FinSearch\Validators\ExportConfigurationBase;
use FINDOLOGIC\Shopware6Common\Export\AbstractExport;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ExportController extends AbstractController
{
    protected LoggerInterface $logger;

    protected HeaderHandler $headerHandler;

    protected CacheItemPoolInterface $cache;

    protected EntityRepository $customerGroupRepository;

    protected EntityRepository $categoryRepository;

    protected EntityRepository $productRepository;

    protected ProductStreamBuilder $productStreamBuilder;

    protected ExportConfigurationBase $exportConfig;

    protected ?SalesChannelService $salesChannelService;

    protected ?SalesChannelContext $salesChannelContext;

    protected Config $pluginConfig;

    protected ExportContext $exportContext;

    protected DynamicProductGroupService $dynamicProductGroupService;

    protected ProductSearcher $productSearcher;

    protected ExportItemAdapter $exportItemAdapter;

    /** @var XmlExport|ProductIdExport */
    protected AbstractExport $export;

    public function __construct(
        LoggerInterface $logger,
        HeaderHandler $headerHandler,
        CacheItemPoolInterface $cache,
        EntityRepository $customerGroupRepository,
        EntityRepository $categoryRepository,
        EntityRepository $productRepository,
        ProductStreamBuilder $productStreamBuilder
    ) {
        $this->logger = $logger;
        $this->headerHandler = $headerHandler;
        $this->cache = $cache;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->productStreamBuilder = $productStreamBuilder;
    }

    /**
     * @Route("/findologic", name="frontend.findologic.export", options={"seo"="false"}, methods={"GET"})
     */
    public function export(Request $request, ?SalesChannelContext $context): Response
    {
        $errorResponse = $this->initialize($request, $context) ?? $this->validateDynamicGroupPrecondition($request);
        if ($errorResponse) {
            return $errorResponse;
        }

        return $this->doExport();
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

    protected function initialize(Request $request, ?SalesChannelContext $context): ?Response
    {
        $this->preInitialize($request, $context);
        if ($errorResponse = $this->validate()) {
            return $errorResponse;
        }

        $this->postInitialize($request);

        return null;
    }

    protected function preInitialize(Request $request, ?SalesChannelContext $context): void
    {
        $this->exportConfig = ExportConfigurationBase::getInstance($request);
        $this->buildSalesChannelContext($context);
    }

    protected function postInitialize(Request $request): void
    {
        $this->buildPluginConfig();
        $this->buildExportContext();
        $this->buildDynamicProductGroupService();

        $this->exportItemAdapter = $this->container->get(ExportItemAdapter::class);
        $this->productSearcher = $this->container->get(ProductSearcher::class);

        $this->buildExport();
        $this->manipulateRequestWithSalesChannelInformation($request);
    }

    protected function buildSalesChannelContext(?SalesChannelContext $context): void
    {
        $this->salesChannelService = $context ? $this->container->get(SalesChannelService::class) : null;
        $this->salesChannelContext = $this->salesChannelService ? $this->salesChannelService
            ->getSalesChannelContext($context, $this->exportConfig->getShopkey()) : null;
        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);
    }

    protected function buildPluginConfig(): void
    {
        /** @var Config $config */
        $config = $this->container->get(Config::class);
        if ($this->salesChannelContext) {
            $config->initializeBySalesChannel($this->salesChannelContext);
        }

        $this->pluginConfig = $config;
    }

    protected function buildExportContext(): void
    {
        $navigationCategory =  Utils::fetchNavigationCategoryFromSalesChannel(
            $this->categoryRepository,
            $this->salesChannelContext->getSalesChannel()
        );

        $this->exportContext = new ExportContext(
            $this->exportConfig->getShopkey(),
            $this->salesChannelContext->getSalesChannelId(),
            $this->salesChannelContext->getCurrencyId(),
            $navigationCategory ? $navigationCategory->getId() : '',
            $navigationCategory ? $navigationCategory->getBreadcrumb() : '',
            $this->getAllCustomerGroups(),
            true // TODO: Fetch real value
        );
        $this->container->set(ExportContext::class, $this->exportContext);
    }

    protected function buildDynamicProductGroupService(): void
    {
        $this->dynamicProductGroupService = new DynamicProductGroupService(
            $this->productRepository,
            $this->categoryRepository,
            $this->productStreamBuilder,
            $this->salesChannelContext,
            $this->exportConfig,
            $this->cache,
            $this->exportContext,
        );
        $this->container->set(DynamicProductGroupService::class, $this->dynamicProductGroupService);
    }

    protected function buildExport(): void
    {
        $this->export = Export::getInstance(
            $this->exportConfig->getProductId() ? Export::TYPE_PRODUCT_ID : Export::TYPE_XML,
            $this->dynamicProductGroupService,
            $this->productSearcher,
            $this->exportItemAdapter,
            $this->container,
            $this->logger
        );
    }

    protected function validate(): ?Response
    {
        $messages = $this->validateStateAndGetErrorMessages();
        if (count($messages) > 0) {
            $errorHandler = new ProductErrorHandler();
            $errorHandler->getExportErrors()->addGeneralErrors($messages);

            return AbstractExport::buildErrorResponse($errorHandler, $this->headerHandler->getHeaders());
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

    protected function validateExportConfiguration(): array
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

    protected function doExport(): Response
    {
        $products = $this->productSearcher->findVisibleProducts(
            $this->exportConfig->getCount(),
            $this->exportConfig->getStart(),
            $this->exportConfig->getProductId()
        );

        return $this->export->buildResponse(
            $this->export->buildItems($products),
            $this->exportConfig->getStart(),
            $this->productSearcher->findTotalProductCount(),
            $this->headerHandler->getHeaders()
        );
    }

    private function manipulateRequestWithSalesChannelInformation(Request $originalRequest): void
    {
        $request = $this->salesChannelService->getRequest($originalRequest, $this->salesChannelContext);
        $attributes = $request->attributes->all();

        $originalRequest->attributes->replace($attributes);
    }

    protected function getAllCustomerGroups(): array
    {
        return $this->customerGroupRepository
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();
    }
}
