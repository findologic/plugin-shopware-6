<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Exceptions\Export\UnknownShopkeyException;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;

class ExportController extends AbstractController implements EventSubscriberInterface
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Router */
    private $router;

    /** @var HeaderHandler */
    private $headerHandler;

    /** @var SalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ExportConfiguration */
    private $config;

    /** @var ProductService */
    private $productService;

    /** @var CustomerGroupEntity[] */
    private $customerGroups = [];

    /** @var Config */
    private $pluginConfig;

    /** @var XmlExport */
    private $xmlExport;

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
     */
    public function export(Request $request, SalesChannelContext $context): Response
    {
        try {
            $this->initialize($request, $context);
        } catch (InvalidArgumentException | UnknownShopkeyException $e) {
            $errorHandler = $this->addAndGetProductErrorHandler();
            $this->logger->warning($e->getMessage());
            return $this->buildErrorResponseWithHeaders($errorHandler);
        }

        return $this->doExport();
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     * @throws UnknownShopkeyException
     */
    protected function initialize(Request $request, SalesChannelContext $context): void
    {
        $this->config = $this->getExportConfiguration($request);
        /** @var SalesChannelService $salesChannelService */
        $salesChannelService = $this->container->get(SalesChannelService::class);
        $this->salesChannelContext = $salesChannelService->getSalesChannelContext($context, $this->config->getShopkey());
        $this->productService = ProductService::getInstance($this->container, $this->salesChannelContext);
        $this->customerGroups = $this->container->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();
        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->pluginConfig = $this->getPluginConfig();

        $this->xmlExport = new XmlExport($this->router, $this->container, $this->logger, $this->pluginConfig->getCrossSellingCategories());
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

        $items = $this->xmlExport->buildXmlItems(
            $products->getElements(),
            $this->config->getShopkey(),
            $this->customerGroups
        );

        return $this->xmlExport->buildXmlResponse(
            $items,
            $this->config->getStart(),
            $this->productService->getTotalProductCount(),
            $this->headerHandler->getHeaders()
        );
    }

    /**
     * Searches all products for the given "productId" query parameter. Searches all fields in the "ordernumber" field.
     *
     * @return Response|JsonResponse Returns an XML if all found products can be properly exported. Otherwise a
     * JSON response with a detailed error description will be returned.
     */
    protected function doProductIdSearch(): Response
    {
        $limit = $this->config->getCount();
        $offset = $this->config->getStart();
        $productId = $this->config->getProductId();

        $errorHandler = $this->addAndGetProductErrorHandler();
        $products = $this->getProductsMatchingProductId($limit, $offset, $productId);
        if ($products->count() === 0) {
            return $this->buildErrorResponseWithHeaders($errorHandler);
        }

        $items = $this->xmlExport->buildXmlItems(
            $products->getElements(),
            $this->config->getShopkey(),
            $this->customerGroups
        );
        if (!$errorHandler->getExportErrors()->hasErrors()) {
            return $this->xmlExport->buildXmlResponse(
                $items,
                $this->config->getStart(),
                $this->productService->getTotalProductCount(),
                $this->headerHandler->getHeaders()
            );
        }

        return $this->buildErrorResponseWithHeaders($errorHandler);
    }

    private function getProductsMatchingProductId(int $limit, int $offset, ?string $productId): EntitySearchResult
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

    private function getExportConfiguration(Request $request): ExportConfiguration
    {
        $config = ExportConfiguration::getInstance($request);
        $this->validateConfiguration($config);

        return $config;
    }

    private function getPluginConfig(): Config
    {
        /** @var Config $config */
        $config = $this->container->get(Config::class);
        $config->initializeBySalesChannel($this->salesChannelContext->getSalesChannel()->getId());

        return $config;
    }

    protected function buildErrorResponseWithHeaders(ProductErrorHandler $errorHandler): JsonResponse
    {
        return new JsonResponse(
            $errorHandler->getExportErrors()->buildErrorResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $this->headerHandler->getHeaders([
                HeaderHandler::CONTENT_TYPE_HEADER => HeaderHandler::CONTENT_TYPE_JSON
            ])
        );
    }

    protected function buildXmlResponseWithHeaders(string $xml): Response
    {
        return new Response($xml, Response::HTTP_OK, $this->headerHandler->getHeaders());
    }

    protected function addAndGetProductErrorHandler(): ProductErrorHandler
    {
        $errorHandler = new ProductErrorHandler();
        $this->logger->pushHandler($errorHandler);

        return $errorHandler;
    }

    private function validateConfiguration(ExportConfiguration $config): void
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($config);

        if ($violations->count() > 0) {
            $messages = array_map(function (ConstraintViolation $violation) {
                return sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }, current((array_values((array)$violations))));

            throw new InvalidArgumentException(implode(', ', $messages));
        }
    }
}
