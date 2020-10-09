<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasCrossSellingCategoryException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductInvalidException;
use FINDOLOGIC\FinSearch\Exceptions\Export\UnknownShopkeyException;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Export\XmlProduct;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validation;

class ExportController extends AbstractController implements EventSubscriberInterface
{
    private const DEFAULT_START_PARAM = 0;
    private const DEFAULT_COUNT_PARAM = 20;

    /** @var LoggerInterface */
    protected $logger;

    /** @var Router */
    private $router;

    /** @var HeaderHandler */
    private $headerHandler;

    private $salesChannelContextFactory;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ExportConfiguration */
    private $config;

    /** @var ProductService */
    private $productService;

    /** @var CustomerGroupEntity[] */
    private $customerGroups = [];

    /** @var string[] */
    private $crossSellingCategories = [];

    public function __construct(
        LoggerInterface $logger,
        RouterInterface $router,
        HeaderHandler $headerHandler,
        SalesChannelContextFactory $salesChannelContextFactory
    ) {
        $this->logger = $logger;
        $this->router = $router;
        $this->headerHandler = $headerHandler;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
    }

    public static function getSubscribedEvents(): array
    {
        return [];
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/findologic", name="frontend.findologic.export", options={"seo"="false"}, methods={"GET"})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    public function export(Request $request, SalesChannelContext $context): Response
    {
        $this->initialize($request, $context);

        return $this->doExport();
    }

    protected function doExport(): Response
    {
        if ($this->config->getProductId()) {
            return $this->doProductIdSearch();
        }

        return $this->doProductExport();
    }

    protected function doProductExport(): Response
    {
        $products = $this->productService->searchVisibleProducts($this->config->getCount(), $this->config->getStart());
        $xml = $this->exportProducts($products);

        return $this->buildXmlResponseWithHeaders($xml);
    }

    /**
     * @return Response|JsonResponse Returns an XML if all found products can be properly exported. Otherwise a
     * JSON response with a detailed error description will be returned.
     */
    protected function doProductIdSearch(): Response
    {
        $limit = $this->config->getCount();
        $offset = $this->config->getStart();
        $productId = $this->config->getProductId();

        $errorHandler = new ProductErrorHandler();
        $this->logger->pushHandler($errorHandler);

        $products = $this->getProductsMatchingProductId($limit, $offset, $productId);
        if ($products->count() === 0) {
            return $this->buildErrorResponseWithHeaders($errorHandler);
        }

        $xml = $this->exportProducts($products);
        if (!$errorHandler->getExportErrors()->hasErrors()) {
            return $this->buildXmlResponseWithHeaders($xml);
        }

        return $this->buildErrorResponseWithHeaders($errorHandler);
    }

    protected function getProductsMatchingProductId(int $limit, int $offset, ?string $productId): EntitySearchResult
    {
        $products = $this->productService->searchVisibleProducts($limit, $offset, $productId);
        if ($products->count() === 0) {
            $products = $this->productService->searchAllProducts($limit, $offset, $productId);

            if ($products->count() > 0) {
                $this->logger->warning('Product(s) is/are not available for search.');
            } else {
                $this->logger->warning('No product could be found.');
            }
        }

        return $products;
    }

    private function getConfiguration(Request $request): ExportConfiguration
    {
        $config = new ExportConfiguration();
        $config->setShopkey($request->query->get('shopkey'));
        $config->setStart($request->query->getInt('start', self::DEFAULT_START_PARAM));
        $config->setCount($request->query->getInt('count', self::DEFAULT_COUNT_PARAM));
        $config->setProductId($request->query->get('productId'));

        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($config);
        if ($violations->count() > 0) {
            throw new InvalidArgumentException($violations->__toString());
        }

        return $config;
    }

    private function getProductService(): ProductService
    {
        if ($this->container->has('fin_search.product_service')) {
            $productService = $this->container->get('fin_search.product_service');
        } else {
            $productService = new ProductService($this->container, $this->salesChannelContext);
            $this->container->set('fin_search.product_service', $productService);
        }

        if (!$productService->getSalesChannelContext()) {
            $productService->setSalesChannelContext($this->salesChannelContext);
        }

        return $productService;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    private function getSalesChannelContext(
        SalesChannelContext $currentContext
    ): SalesChannelContext {
        /** @var EntityRepository $systemConfigRepository */
        $systemConfigRepository = $this->container->get('system_config.repository');
        $systemConfigEntities = $systemConfigRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('configurationKey', 'FinSearch.config.shopkey')),
            $currentContext->getContext()
        );

        /** @var SystemConfigEntity $systemConfigEntity */
        foreach ($systemConfigEntities as $systemConfigEntity) {
            if ($systemConfigEntity->getConfigurationValue() === $this->config->getShopkey()) {
                // If there is no sales channel assigned, we will return the current context
                if ($systemConfigEntity->getSalesChannelId() === null) {
                    return $currentContext;
                }

                return $this->salesChannelContextFactory->create(
                    $currentContext->getToken(),
                    $systemConfigEntity->getSalesChannelId()
                );
            }
        }

        throw new UnknownShopkeyException(sprintf(
            'Given shopkey "%s" is not assigned to any shop',
            $this->config->getShopkey()
        ));
    }

    /**
     * @param CustomerGroupEntity[] $customerGroups
     *
     * @return Item[]
     */
    private function buildXmlProducts(
        EntitySearchResult $productEntities,
        string $shopkey,
        array $customerGroups
    ): array {
        $items = [];

        /** @var ProductEntity $productEntity */
        foreach ($productEntities as $productEntity) {
            $item = $this->exportSingleItem($productEntity, $shopkey, $customerGroups);
            if (!$item) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    private function handleProductInvalidException(ProductInvalidException $e): void {
        switch (get_class($e)) {
            case AccessEmptyPropertyException::class:
                $message = sprintf(
                    'Product with id %s was not exported because the property does not exist',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoAttributesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no attributes',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoNameException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no name set',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoPricesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no price associated to it',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoCategoriesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no categories assigned',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasCrossSellingCategoryException::class:
                $message = sprintf(
                    'Product with id %s (%s) was not exported because it ' .
                    'is assigned to cross selling category %s (%s)',
                    $e->getProduct()->getId(),
                    $e->getProduct()->getName(),
                    $e->getCategory()->getId(),
                    implode(' > ', $e->getCategory()->getBreadcrumb())
                );
                break;
            default:
                $message = sprintf(
                    'Product with id %s could not be exported.',
                    $e->getProduct()->getId()
                );
        }

        $this->logger->warning($message, ['exception' => $e]);
    }

    private function getConfig(string $config, ?string $salesChannelId)
    {
        return $this->container->get(SystemConfigService::class)->get(
            sprintf('FinSearch.config.%s', $config),
            $salesChannelId
        );
    }

    protected function initialize(Request $request, SalesChannelContext $context): void
    {
        $this->config = $this->getConfiguration($request);
        $this->salesChannelContext = $this->getSalesChannelContext($context);
        $this->productService = $this->getProductService();
        $this->customerGroups = $this->container->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();
        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->crossSellingCategories = $this->getConfig(
            'crossSellingCategories',
            $this->salesChannelContext->getSalesChannel()->getId()
        );
    }

    protected function buildErrorResponseWithHeaders(ProductErrorHandler $errorHandler): JsonResponse
    {
        return new JsonResponse(
            $errorHandler->getExportErrors()->buildErrorResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $this->headerHandler->getHeaders([
                HeaderHandler::CONTENT_TYPE_HEADER => 'application/json'
            ])
        );
    }

    protected function exportProducts(EntitySearchResult $products): string
    {
        $items = $this->buildXmlProducts($products, $this->config->getShopkey(), $this->customerGroups);
        $xmlExporter = Exporter::create(Exporter::TYPE_XML);

        return $xmlExporter->serializeItems(
            $items,
            $this->config->getStart(),
            count($items),
            $this->productService->getTotalProductCount()
        );
    }

    protected function buildXmlResponseWithHeaders(string $xml): Response
    {
        return new Response($xml, Response::HTTP_OK, $this->headerHandler->getHeaders());
    }

    private function exportSingleItem(
        ProductEntity $productEntity,
        string $shopkey,
        array $customerGroups
    ): ?Item {
        try {
            if (!empty($this->crossSellingCategories)) {
                $categories = $productEntity->getCategories();
                $category = $categories ? $categories->first() : null;
                $categoryId = $category ? $category->getId() : null;

                if (in_array($categoryId, $this->crossSellingCategories, false)) {
                    throw new ProductHasCrossSellingCategoryException($productEntity, $category);
                }
            }

            $xmlProduct = new XmlProduct(
                $productEntity,
                $this->router,
                $this->container,
                $this->salesChannelContext->getContext(),
                $shopkey,
                $customerGroups
            );
            return $xmlProduct->getXmlItem();
        } catch (ProductInvalidException $e) {
            $this->handleProductInvalidException($e);
            return null;
        }
    }
}
