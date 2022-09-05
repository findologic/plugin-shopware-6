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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
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

    private SalesChannelRepositoryInterface $productRepository;

    private ServiceConfigResource $serviceConfigResource;

    private string $shopwareVersion;

    private Config $config;

    public function __construct(
        AbstractProductSearchRoute $decorated,
        ProductSearchBuilderInterface $searchBuilder,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelRepositoryInterface $productRepository,
        ProductDefinition $definition,
        RequestCriteriaBuilder $criteriaBuilder,
        ServiceConfigResource $serviceConfigResource,
        FindologicConfigService $findologicConfigService,
        string $shopwareVersion,
        ?Config $config = null
    ) {
        $this->decorated = $decorated;
        $this->searchBuilder = $searchBuilder;
        $this->eventDispatcher = $eventDispatcher;
        $this->productRepository = $productRepository;
        $this->definition = $definition;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->serviceConfigResource = $serviceConfigResource;
        $this->shopwareVersion = $shopwareVersion;
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(
        Request $request,
        SalesChannelContext $context,
        ?Criteria $criteria = null
    ): ProductSearchRouteResponse {

        if (Utils::versionGreaterOrEqual('6.4.0.0', $this->shopwareVersion)) {
            $this->addElasticSearchContext($context);
        }

        $criteria ??= $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $context->getContext()
        );

        $this->config->initializeBySalesChannel($context);
        $shouldHandleRequest = Utils::shouldHandleRequest(
            $request,
            $context->getContext(),
            $this->serviceConfigResource,
            $this->config
        );

        $criteria->addFilter(
            new ProductAvailableFilter(
                $context->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $this->searchBuilder->build($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductSearchCriteriaEvent($request, $criteria, $context)
        );

        if (!$shouldHandleRequest) {
            return $this->decorated->load($request, $context, $criteria);
        }

        $query = $request->query->get('search');
        $result = $this->doSearch($criteria, $context, $query);
        $result = ProductListingResult::createFrom($result);
        $result->addCurrentFilter('search', $query);

        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $context)
        );

        return new ProductSearchRouteResponse($result);
    }

    protected function doSearch(Criteria $criteria, SalesChannelContext $context, ?string $query): EntitySearchResult
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
        $context->getContext()->addState(Context::STATE_ELASTICSEARCH_AWARE);
    }
}
