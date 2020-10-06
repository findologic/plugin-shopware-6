<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Exceptions\EmptyValueNotAllowedException;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\FinSearch\Exceptions\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasCrossSellingCategoryException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Exceptions\UnknownShopkeyException;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\XmlProduct;
use FINDOLOGIC\FinSearch\Utils\Utils;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;
use Throwable;

class ExportController extends AbstractController implements EventSubscriberInterface
{
    private const DEFAULT_START_PARAM = 0;
    private const DEFAULT_COUNT_PARAM = 20;

    /** @var LoggerInterface */
    protected $logger;

    /** @var Router */
    private $router;

    /**
     * @var HeaderHandler
     */
    private $headerHandler;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    private $salesChannelContextFactory;

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
        $this->validateParams($request);
        $shopkey = $request->get('shopkey');
        // We can safely cast the values here as integers because the validation is already taken care of in the
        // previous step so if we reach till here, it means there are no invalid strings being passed as parameter here
        $start = (int)$request->get('start', self::DEFAULT_START_PARAM);
        $count = (int)$request->get('count', self::DEFAULT_COUNT_PARAM);

        $this->salesChannelContext = $this->getSalesChannelContextByShopkey($shopkey, $context);

        $totalProductCount = $this->getTotalProductCount();
        $productEntities = $this->getProductsFromShop($start, $count);
        $customerGroups = $this->container->get('customer_group.repository')
            ->search(new Criteria(), $this->salesChannelContext->getContext())->getElements();

        $this->container->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $items = $this->buildXmlProducts($productEntities, $shopkey, $customerGroups);

        $xmlExporter = Exporter::create(Exporter::TYPE_XML);

        $response = $xmlExporter->serializeItems(
            $items,
            $start,
            count($items),
            $totalProductCount
        );

        return new Response($response, 200, $this->headerHandler->getHeaders());
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function getProductCriteria(
        ?int $offset = null,
        ?int $limit = null
    ): Criteria {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parent.id', null));
        $criteria->addFilter(
            new ProductAvailableFilter(
                $this->salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $criteria = Utils::addProductAssociations($criteria);

        if ($offset !== null) {
            $criteria->setOffset($offset);
        }
        if ($limit !== null) {
            $criteria->setLimit($limit);
        }

        return $criteria;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function getTotalProductCount(): int
    {
        $criteria = $this->getProductCriteria();

        /** @var IdSearchResult $result */
        $result = $this->container->get('product.repository')->searchIds(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        return $result->getTotal();
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function getProductsFromShop(
        ?int $start,
        ?int $count
    ): EntitySearchResult {
        if ($start === null) {
            $start = self::DEFAULT_START_PARAM;
        }
        if ($count === null) {
            $count = self::DEFAULT_COUNT_PARAM;
        }

        $criteria = $this->getProductCriteria($start, $count);

        return $this->container->get('product.repository')->search($criteria, $this->salesChannelContext->getContext());
    }

    private function validateParams(Request $request): void
    {
        $shopkey = $request->get('shopkey');
        $start = $request->get('start', self::DEFAULT_START_PARAM);
        $count = $request->get('count', self::DEFAULT_COUNT_PARAM);

        $validator = Validation::createValidator();
        $shopkeyViolations = $validator->validate(
            $shopkey,
            [
                new NotBlank(),
                new Assert\Regex(
                    [
                        'pattern' => '/^[A-F0-9]{32}$/',
                    ]
                ),
            ]
        );
        if (count($shopkeyViolations) > 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Required argument "shopkey" was not given, or does not match the shopkey schema "%s"',
                    $shopkey
                )
            );
        }

        $startViolations = $validator->validate(
            $start,
            [
                new Assert\Type(
                    [
                        'type' => 'numeric',
                        'message' => 'The value {{ value }} is not a valid {{ type }}',
                    ]
                ),
                new Assert\GreaterThanOrEqual(
                    [
                        'value' => 0,
                        'message' => 'The value {{ value }} is not greater than or equal to zero',
                    ]
                ),
            ]
        );
        if (count($startViolations) > 0) {
            throw new InvalidArgumentException($startViolations->get(0)->getMessage());
        }

        $countViolations = $validator->validate(
            $count,
            [
                new Assert\Type(
                    [
                        'type' => 'numeric',
                        'message' => 'The value {{ value }} is not a valid {{ type }}',
                    ]
                ),
                new Assert\GreaterThan(
                    [
                        'value' => 0,
                        'message' => 'The value {{ value }} is not greater than zero',
                    ]
                ),
            ]
        );
        if (count($countViolations) > 0) {
            throw new InvalidArgumentException($countViolations->get(0)->getMessage());
        }
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    private function getSalesChannelContextByShopkey(
        string $shopkey,
        SalesChannelContext $currentContext
    ): SalesChannelContext {
        $systemConfigRepository = $this->container->get('system_config.repository');
        $systemConfigEntities = $systemConfigRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('configurationKey', 'FinSearch.config.shopkey')),
            $currentContext->getContext()
        );

        /** @var SystemConfigEntity $systemConfigEntity */
        foreach ($systemConfigEntities as $systemConfigEntity) {
            if ($systemConfigEntity->getConfigurationValue() === $shopkey) {
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

        throw new UnknownShopkeyException(sprintf('Given shopkey "%s" is not assigned to any shop', $shopkey));
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

        $crossSellingCategories = $this->getConfig(
            'crossSellingCategories',
            $this->salesChannelContext->getSalesChannel()->getId()
        );

        /** @var ProductEntity $productEntity */
        foreach ($productEntities as $productEntity) {
            try {
                $categories = $productEntity->getCategories();
                $category = $categories ? $categories->first() : null;
                $categoryId = $category ? $category->getId() : null;
                if (!empty($crossSellingCategories) && in_array($categoryId, $crossSellingCategories, false)) {
                    throw new ProductHasCrossSellingCategoryException();
                }
                $xmlProduct = new XmlProduct(
                    $productEntity,
                    $this->router,
                    $this->container,
                    $this->salesChannelContext->getContext(),
                    $shopkey,
                    $customerGroups
                );
                $items[] = $xmlProduct->getXmlItem();
            } catch (AccessEmptyPropertyException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id "%s" was not exported because the property does not exist',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasNoAttributesException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id "%s" was not exported because it has no attributes',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasNoNameException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id "%s" was not exported because it has no name set',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasNoPricesException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id "%s" was not exported because it has no price associated to it',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasNoCategoriesException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id "%s" was not exported because it has no categories assigned',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasCrossSellingCategoryException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id %s (%s) was not exported because it ' .
                        'is assigned to cross selling category %s (%s)',
                        $productEntity->getId(),
                        $productEntity->getName(),
                        $category->getId(),
                        implode(' > ', $category->getBreadcrumb())
                    )
                );
            } catch (EmptyValueNotAllowedException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id "%s" could not be exported. It appears to have empty values assigned to it. ' .
                        'If you see this message in your logs, please report this as a bug.',
                        $productEntity->getId()
                    )
                );
            } catch (Throwable $e) {
                $this->logger->warning(
                    sprintf(
                        'Error while exporting the product with id "%s". If you see this message in your logs, ' .
                        'please report this as a bug. Error message: %s',
                        $productEntity->getId(),
                        $e->getMessage()
                    )
                );
            }
        }

        return $items;
    }

    private function getConfig(string $config, ?string $salesChannelId)
    {
        return $this->container->get(SystemConfigService::class)->get(
            sprintf('FinSearch.config.%s', $config),
            $salesChannelId
        );
    }
}
