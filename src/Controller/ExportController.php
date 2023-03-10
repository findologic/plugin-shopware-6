<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Export\Handlers\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\Search\CategorySearcher;
use FINDOLOGIC\FinSearch\Export\Services\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\Services\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Adapters\ExportItemAdapter;
use FINDOLOGIC\Shopware6Common\Export\Config\ImplementationType;
use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\Shopware6Common\Export\Responses\PreconditionFailedResponse;
use FINDOLOGIC\Shopware6Common\Export\Types\AbstractExport;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Types\ProductIdExport;
use FINDOLOGIC\Shopware6Common\Export\Types\XmlExport;
use FINDOLOGIC\Shopware6Common\Export\Validation\ExportConfigurationBase;
use FINDOLOGIC\Shopware6Common\Export\Validation\OffsetExportConfiguration;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
use Vin\ShopwareSdk\Data\Entity\Category\CategoryEntity;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupCollection;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupEntity;
use Vin\ShopwareSdk\Data\Entity\SalesChannel\SalesChannelEntity;

class ExportController extends AbstractController
{
    protected LoggerInterface $logger;

    protected EventDispatcherInterface $eventDispatcher;

    protected CacheItemPoolInterface $cache;

    protected HeaderHandler $headerHandler;

    protected ProductStreamBuilder $productStreamBuilder;

    protected SystemConfigService $systemConfigService;

    protected EntityRepository $customerGroupRepository;

    protected EntityRepository $categoryRepository;

    protected EntityRepository $productRepository;

    protected OffsetExportConfiguration $exportConfig;

    protected ?SalesChannelService $salesChannelService;

    protected ?SalesChannelContext $salesChannelContext;

    protected PluginConfig $pluginConfig;

    protected ExportContext $exportContext;

    protected DynamicProductGroupService $dynamicProductGroupService;

    protected ProductSearcher $productSearcher;

    protected CategorySearcher $categorySearcher;

    protected ExportItemAdapter $exportItemAdapter;

    /** @var XmlExport|ProductIdExport */
    protected AbstractExport $export;

    public function __construct(
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        CacheItemPoolInterface $cache,
        HeaderHandler $headerHandler,
        ProductStreamBuilder $productStreamBuilder,
        SystemConfigService $systemConfigService,
        EntityRepository $customerGroupRepository,
        EntityRepository $categoryRepository,
        EntityRepository $productRepository
    ) {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->cache = $cache;
        $this->headerHandler = $headerHandler;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->systemConfigService = $systemConfigService;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @Route(
     *     "/findologic",
     *     name="frontend.findologic.export",
     *     options={"seo"="false"},
     *     methods={"GET"},
     *     defaults={"_routeScope"={"storefront"}}
     * )
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
     * @Route(
     *     "/findologic/dynamic-product-groups",
     *     name="frontend.findologic.export.dynamic_product_groups",
     *     options={"seo"="false"},
     *     methods={"GET"},
     *     defaults={"_routeScope"={"storefront"}}
     * )
     */
    public function exportProductGroup(Request $request, ?SalesChannelContext $context): Response
    {
        $this->initialize($request, $context);
        if ($errorResponse = $this->validate()) {
            return $errorResponse;
        }

        $total = $this->warmUpDynamicProductGroupsAndGetTotal();

        return new JsonResponse(
            [
                'meta' => [
                    'start' => $this->exportConfig->getStart(),
                    'count' => $this->exportConfig->getCount(),
                    'total' => $total
                ]
            ]
        );
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
        /** @var OffsetExportConfiguration $exportConfig */
        $exportConfig = ExportConfigurationBase::getInstance($request);
        $this->exportConfig = $exportConfig;
        $this->buildSalesChannelContext($context);
    }

    protected function postInitialize(Request $request): void
    {
        $this->buildPluginConfig();
        $this->buildExportContext();

        $this->categorySearcher = $this->container->get(CategorySearcher::class);

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

        $this->pluginConfig = PluginConfig::createFromArray($config->jsonSerialize());
        $this->container->set(PluginConfig::class, $this->pluginConfig);
    }

    protected function buildExportContext(): void
    {
        $navigationCategory =  Utils::fetchNavigationCategoryFromSalesChannel(
            $this->categoryRepository,
            $this->salesChannelContext->getSalesChannel()
        );

        /** @var SalesChannelEntity $salesChannelEntity */
        $salesChannelEntity = Utils::createSdkEntity(
            SalesChannelEntity::class,
            $this->salesChannelContext->getSalesChannel()
        );
        /** @var CategoryEntity $navigationCategoryEntity */
        $navigationCategoryEntity = Utils::createSdkEntity(CategoryEntity::class, $navigationCategory);

        $this->exportContext = new ExportContext(
            $this->exportConfig->getShopkey(),
            $salesChannelEntity,
            $navigationCategoryEntity,
            $this->getAllCustomerGroups(),
            $this->shouldHideProductsOutOfStock(),
            ImplementationType::PLUGIN,
        );
        $this->container->set(ExportContext::class, $this->exportContext);
    }

    protected function buildDynamicProductGroupService(): void
    {
        $this->dynamicProductGroupService = new DynamicProductGroupService(
            $this->productRepository,
            $this->categorySearcher,
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
        $this->export = AbstractExport::getInstance(
            $this->exportConfig->getProductId() ? AbstractExport::TYPE_PRODUCT_ID : AbstractExport::TYPE_XML,
            $this->dynamicProductGroupService,
            $this->productSearcher,
            $this->pluginConfig,
            $this->exportItemAdapter,
            $this->logger,
            $this->eventDispatcher
        );
    }

    protected function validate(): ?Response
    {
        $messages = $this->validateStateAndGetErrorMessages();
        if (count($messages) > 0) {
            $errorHandler = new ProductErrorHandler();
            $errorHandler->getExportErrors()->addGeneralErrors($messages);

            return AbstractExport::buildErrorResponse($errorHandler);
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

    protected function validateDynamicGroupPrecondition(Request $request): ?Response
    {
        $excludeProductGroups = $request->query->getBoolean('excludeProductGroups');
        if (!$excludeProductGroups && !$this->dynamicProductGroupService->areDynamicProductGroupsCached()) {
            return new PreconditionFailedResponse('findologic');
        }

        return null;
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

    protected function doExport(): Response
    {
        $products = $this->productSearcher->findVisibleProducts(
            $this->exportConfig->getCount(),
            $this->exportConfig->getStart(),
            $this->exportConfig->getProductId()
        )->getElements();

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

    protected function getAllCustomerGroups(): CustomerGroupCollection
    {
        $customerGroups = $this->customerGroupRepository
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getEntities();

        /** @var CustomerGroupCollection $customerGroupCollection */
        $customerGroupCollection = Utils::createSdkCollection(
            CustomerGroupCollection::class,
            CustomerGroupEntity::class,
            $customerGroups
        );

        return $customerGroupCollection;
    }

    protected function shouldHideProductsOutOfStock(): bool
    {
        return !!$this->systemConfigService->get(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $this->salesChannelContext->getSalesChannelId()
        );
    }
}
