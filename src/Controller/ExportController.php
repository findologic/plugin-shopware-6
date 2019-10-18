<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\FinSearch\Exceptions\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoDateAddedException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoDescriptionException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoImagesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoKeywordsException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoOrdernumbersException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPropertiesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoURLException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoUserGroupsException;
use FINDOLOGIC\FinSearch\Exceptions\UnknownShopkeyException;
use FINDOLOGIC\FinSearch\Export\XmlProduct;
use FINDOLOGIC\FinSearch\Utils\Utils;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

class ExportController extends AbstractController implements EventSubscriberInterface
{
    private const
        DEFAULT_START_PARAM = 0,
        DEFAULT_COUNT_PARAM = 20;

    /** @var LoggerInterface */
    protected $logger;

    /** @var Router */
    private $router;

    public function __construct(LoggerInterface $logger, Router $router)
    {
        $this->logger = $logger;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [];
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/findologic", name="frontend.findologic.export", options={"seo"="false"}, methods={"GET"})
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

        $salesChannelContext = $this->getSalesChannelContext($shopkey, $context);

        $totalProductCount = $this->getTotalProductCount($salesChannelContext);
        $productEntities = $this->getProductsFromShop($salesChannelContext, $start, $count);
        $customerGroups = $this->container->get('customer_group.repository')
            ->search(new Criteria(), $salesChannelContext->getContext())->getElements();

        $items = $this->buildXmlProducts($productEntities, $salesChannelContext, $shopkey, $customerGroups);

        $xmlExporter = Exporter::create(Exporter::TYPE_XML);

        $response = $xmlExporter->serializeItems(
            $items,
            $start,
            $count,
            $totalProductCount
        );

        return new Response($response, 200, ['Content-Type' => 'text/xml']);
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
                new Assert\Regex([
                    'pattern' => '/^[A-F0-9]{32}$/'
                ])
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
                new Assert\Type([
                    'type' => 'numeric',
                    'message' => 'The value {{ value }} is not a valid {{ type }}',
                ]),
                new Assert\GreaterThanOrEqual([
                    'value' => 0,
                    'message' => 'The value {{ value }} is not greater than or equal to zero'
                ])
            ]
        );
        if (count($startViolations) > 0) {
            throw new InvalidArgumentException($startViolations->get(0)->getMessage());
        }

        $countViolations = $validator->validate(
            $count,
            [
                new Assert\Type([
                    'type' => 'numeric',
                    'message' => 'The value {{ value }} is not a valid {{ type }}',
                ]),
                new Assert\GreaterThan([
                    'value' => 0,
                    'message' => 'The value {{ value }} is not greater than zero'
                ])
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
    private function getSalesChannelContext(string $shopkey, SalesChannelContext $currentContext): SalesChannelContext
    {
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

                $factory = new SalesChannelContextFactory(
                    $this->container->get('sales_channel.repository'),
                    $this->container->get('currency.repository'),
                    $this->container->get('customer.repository'),
                    $this->container->get('customer_group.repository'),
                    $this->container->get('country.repository'),
                    $this->container->get('tax.repository'),
                    $this->container->get('customer_address.repository'),
                    $this->container->get('payment_method.repository'),
                    $this->container->get('shipping_method.repository'),
                    Kernel::getConnection(),
                    $this->container->get('country_state.repository'),
                    new TaxDetector()
                );

                return $factory->create($currentContext->getToken(), $systemConfigEntity->getSalesChannelId());
            }
        }

        throw new UnknownShopkeyException(sprintf('Given shopkey "%s" is not assigned to any shop', $shopkey));
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function queryProducts(
        SalesChannelContext $salesChannelContext,
        ?int $offset = null,
        ?int $limit = null
    ): EntitySearchResult {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parent.id', null));
        $criteria->addFilter(new ProductAvailableFilter(
            $salesChannelContext->getSalesChannel()->getId(),
            ProductVisibilityDefinition::VISIBILITY_SEARCH
        ));

        $criteria = Utils::addProductAssociations($criteria);

        if ($offset !== null) {
            $criteria->setOffset($offset);
        }
        if ($limit !== null) {
            $criteria->setLimit($limit);
        }

        return $this->container->get('product.repository')->search($criteria, $salesChannelContext->getContext());
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function getTotalProductCount(SalesChannelContext $salesChannelContext): int
    {
        return $this->queryProducts($salesChannelContext)->getEntities()->count();
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function getProductsFromShop(
        SalesChannelContext $salesChannelContext,
        ?int $start,
        ?int $count
    ): EntitySearchResult {
        if ($start === null) {
            $start = self::DEFAULT_START_PARAM;
        }
        if ($count === null) {
            $count = self::DEFAULT_COUNT_PARAM;
        }

        return $this->queryProducts($salesChannelContext, $start, $count);
    }

    /**
     * @param CustomerGroupEntity[] $customerGroups
     *
     * @return XmlProduct[]
     */
    private function buildXmlProducts(
        EntitySearchResult $productEntities,
        SalesChannelContext $salesChannelContext,
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
                    $salesChannelContext->getContext(),
                    $shopkey,
                    $customerGroups
                );
                $items[] = $xmlProduct->getXmlItem();
            } catch (AccessEmptyPropertyException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id %s was not exported because the property does not exist',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasNoAttributesException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id %s was not exported because it has no attributes',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasNoNameException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id %s was not exported because it has no name set',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasNoPricesException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id %s was not exported because it has no price associated to it',
                        $productEntity->getId()
                    )
                );
            } catch (ProductHasNoCategoriesException $e) {
                $this->logger->warning(
                    sprintf(
                        'Product with id %s was not exported because it has no categories assigned',
                        $productEntity->getId()
                    )
                );
            }
        }

        return $items;
    }
}
