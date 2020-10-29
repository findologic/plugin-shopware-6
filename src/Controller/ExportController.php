<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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

    /** @var Config */
    private $pluginConfig;

    /** @var Export|XmlExport|ProductIdExport */
    private $export;

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
        $this->initialize($request, $context);

        return $this->validateAndDoExport();
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     */
    protected function initialize(Request $request, SalesChannelContext $context): void
    {
        $this->config = ExportConfiguration::getInstance($request);
        $this->salesChannelContext = $this->container->get(SalesChannelService::class)
            ->getSalesChannelContext($context, $this->config->getShopkey());

        $this->productService = ProductService::getInstance($this->container, $this->salesChannelContext);
        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->pluginConfig = $this->getPluginConfig();

        $this->export = Export::getInstance(
            $this->config->getProductId() ? Export::TYPE_PRODUCT_ID : Export::TYPE_XML,
            $this->router,
            $this->container,
            $this->logger,
            $this->pluginConfig->getCrossSellingCategories()
        );
    }

    protected function validateAndDoExport(): Response
    {
        $message = $this->validateStateAndGetErrorMessage();
        if ($message !== null) {
            $errorHandler = new ProductErrorHandler();
            $errorHandler->getExportErrors()->addGeneralError($message);
            return $this->export->buildErrorResponseWithHeaders($errorHandler, $this->headerHandler->getHeaders());
        }

        return $this->doExport();
    }

    protected function doExport(): Response
    {
        $products = $this->productService->searchVisibleProducts(
            $this->config->getCount(),
            $this->config->getStart(),
            $this->config->getProductId()
        );

        $items = $this->export->buildItems(
            $products->getElements(),
            $this->config->getShopkey(),
            $this->productService->getAllCustomerGroups()
        );

        return $this->export->buildResponse(
            $items,
            $this->config->getStart(),
            $this->productService->getTotalProductCount(),
            $this->headerHandler->getHeaders()
        );
    }

    /**
     * Validates the initialized state of the exporter. In case it is not valid, an appropriate message may be
     * returned. In case everything is valid, null may be returned.
     */
    protected function validateStateAndGetErrorMessage(): ?string
    {
        if (($message = $this->validateExportConfiguration($this->config)) !== null) {
            return $message;
        }

        if ($this->salesChannelContext === null) {
            return sprintf(
                'Shopkey %s is not assigned to any sales channel.',
                $this->config->getShopkey()
            );
        }

        return null;
    }

    private function validateExportConfiguration(ExportConfiguration $config): ?string
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($config);

        if ($violations->count() > 0) {
            $messages = array_map(function (ConstraintViolation $violation) {
                return sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }, current((array_values((array)$violations))));

            return implode(' | ', $messages);
        }

        return null;
    }

    private function getPluginConfig(): Config
    {
        /** @var Config $config */
        $config = $this->container->get(Config::class);
        if ($this->salesChannelContext) {
            $config->initializeBySalesChannel($this->salesChannelContext->getSalesChannel()->getId());
        }

        return $config;
    }
}
