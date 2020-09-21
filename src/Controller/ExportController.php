<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductInvalidException;
use FINDOLOGIC\FinSearch\Exceptions\Export\UnknownShopkeyException;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Export\XmlProduct;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
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

    /** @var string[] */
    private $errors = [];

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
        $this->config = $this->getConfiguration($request);
        $this->salesChannelContext = $this->getSalesChannelContext($context);
        $this->productService = $this->getProductService();

        $productEntities = $this->getProductsFromShop();
        $customerGroups = $this->container->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())
            ->getElements();

        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $items = $this->buildXmlProducts($productEntities, $this->config->getShopkey(), $customerGroups);

        if ($this->errors) {
            return new JsonResponse(
                ['errors' => $this->errors],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $this->headerHandler->getHeaders([
                    HeaderHandler::CONTENT_TYPE_HEADER => 'application/json'
                ])
            );
        }

        $xmlExporter = Exporter::create(Exporter::TYPE_XML);
        $response = $xmlExporter->serializeItems(
            $items,
            $this->config->getStart(),
            count($items),
            $this->productService->getTotalProductCount()
        );

        return new Response($response, Response::HTTP_OK, $this->headerHandler->getHeaders());
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function getProductsFromShop(): EntitySearchResult
    {
        $start = $this->config->getStart();
        $count = $this->config->getCount();

        $products = $this->productService->searchVisibleProducts($count, $start, $this->config->getProductId());

        if ($this->config->getProductId() && $products->count() === 0) {
            $products = $this->productService->searchAllProducts($count, $start, $this->config->getProductId());

            if ($products->count() > 0) {
                $this->addErrorMessage('The product could not be exported, since it is not available for search.');
            } else {
                $this->addErrorMessage('No product could be found for the given id.');
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

        if (!$this->productService->getSalesChannelContext()) {
            $this->productService->setSalesChannelContext($this->salesChannelContext);
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
            try {
                $xmlProduct = new XmlProduct(
                    $productEntity,
                    $this->router,
                    $this->container,
                    $this->salesChannelContext->getContext(),
                    $shopkey,
                    $customerGroups
                );
                $items[] = $xmlProduct->getXmlItem();
            } catch (ProductInvalidException $e) {
                $this->handleProductInvalidException($e, $productEntity);
            }
        }

        return $items;
    }

    private function handleProductInvalidException(
        ProductInvalidException $e,
        ProductEntity $failedProduct
    ): void {
        switch (get_class($e)) {
            case AccessEmptyPropertyException::class:
                $message = sprintf(
                    'Product with id %s was not exported because the property does not exist',
                    $failedProduct->getId()
                );
                break;
            case ProductHasNoAttributesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no attributes',
                    $failedProduct->getId()
                );
                break;
            case ProductHasNoNameException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no name set',
                    $failedProduct->getId()
                );
                break;
            case ProductHasNoPricesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no price associated to it',
                    $failedProduct->getId()
                );
                break;
            case ProductHasNoCategoriesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no categories assigned',
                    $failedProduct->getId()
                );
                break;
            default:
                $message = sprintf(
                    'Product with id %s could not be exported.',
                    $failedProduct->getId()
                );
        }

        $this->logger->warning($message);
        $this->addErrorMessage($message);
    }

    private function addErrorMessage(string $message, ?string $id = null): void
    {
        // Only add error messages in case a product id has been supplied.
        if (!$this->config->getProductId()) {
            return;
        }

        $this->errors[] = htmlentities($message);
    }
}
