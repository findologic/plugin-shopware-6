<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search;

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

    /**
     * @var ProductSearchBuilderInterface
     */
    private $searchBuilder;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductDefinition
     */
    private $definition;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var AbstractProductSearchRoute
     */
    private $decorated;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        AbstractProductSearchRoute $decorated,
        ProductSearchBuilderInterface $searchBuilder,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelRepositoryInterface $productRepository,
        ProductDefinition $definition,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->searchBuilder = $searchBuilder;
        $this->eventDispatcher = $eventDispatcher;
        $this->definition = $definition;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->decorated = $decorated;
        $this->productRepository = $productRepository;
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context): ProductSearchRouteResponse
    {
        if (!Utils::isFindologicEnabled($context)) {
            return $this->decorated->load($request, $context);
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new ProductAvailableFilter(
                $context->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $this->searchBuilder->build($request, $criteria, $context);
        $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->definition,
            $context->getContext()
        );

        $this->eventDispatcher->dispatch(
            new ProductSearchCriteriaEvent($request, $criteria, $context)
        );

        $result = $this->doSearch($criteria, $context);
        $result = ProductListingResult::createFrom($result);
        $result->addCurrentFilter('search', $request->query->get('search'));

        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $context)
        );

        return new ProductSearchRouteResponse($result);
    }

    protected function doSearch(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        $this->assignPaginationToCriteria($criteria);

        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $context);
        }

        return $this->fetchProducts($criteria, $context);
    }
}
