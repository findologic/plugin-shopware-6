<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Traits\SearchResultHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductSearchRoute extends AbstractProductSearchRoute
{
    use SearchResultHelper;

    private ProductSearchBuilderInterface $searchBuilder;

    private EventDispatcherInterface $eventDispatcher;

    private ProductDefinition $definition;

    private RequestCriteriaBuilder $criteriaBuilder;

    private AbstractProductSearchRoute $decorated;

    private EntityRepository $productRepository;

    private ServiceConfigResource $serviceConfigResource;

    private Config $config;

    public function __construct(
        AbstractProductSearchRoute $decorated,
        ProductSearchBuilderInterface $searchBuilder,
        EventDispatcherInterface $eventDispatcher,
        EntityRepository $productRepository,
        ProductDefinition $definition,
        RequestCriteriaBuilder $criteriaBuilder,
        ServiceConfigResource $serviceConfigResource,
        FindologicConfigService $findologicConfigService,
        ?Config $config = null
    ) {
        $this->decorated = $decorated;
        $this->searchBuilder = $searchBuilder;
        $this->eventDispatcher = $eventDispatcher;
        $this->productRepository = $productRepository;
        $this->definition = $definition;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(
        Request $request,
        SalesChannelContext $salesChannelContext,
        ?Criteria $criteria = null
    ): ProductSearchRouteResponse {
        $this->addElasticSearchContext($salesChannelContext);

        $criteria ??= $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $salesChannelContext->getContext()
        );

        $this->config->initializeBySalesChannel($salesChannelContext);
        $shouldHandleRequest = Utils::shouldHandleRequest(
            $request,
            $salesChannelContext->getContext(),
            $this->serviceConfigResource,
            $this->config
        );

        $criteria->addFilter(
            new ProductAvailableFilter(
                $salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $this->searchBuilder->build($request, $criteria, $salesChannelContext);

        $this->eventDispatcher->dispatch(
            new ProductSearchCriteriaEvent($request, $criteria, $salesChannelContext)
        );

        if (!$shouldHandleRequest) {
            return $this->decorated->load($request, $salesChannelContext, $criteria);
        }

        $query = $request->query->get('search');
        $result = $this->doSearch($criteria, $salesChannelContext->getContext(), $query);
        $result = ProductListingResult::createFrom($result);
        $result->addCurrentFilter('search', $query);

        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $salesChannelContext)
        );

        return new ProductSearchRouteResponse($result);
    }

    protected function doSearch(Criteria $criteria, Context $context, ?string $query): EntitySearchResult
    {
        $this->assignPaginationToCriteria($criteria);
        $this->addOptionsGroupAssociation($criteria);

        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $context);
        }

        return $this->fetchProducts($criteria, $context, $query);
    }

    public function addElasticSearchContext(SalesChannelContext $context): void
    {
        $context->getContext()->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
    }
}
