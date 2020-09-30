<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Traits\SearchResultHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductListingRoute extends AbstractProductListingRoute
{
    use SearchResultHelper;

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
     * @var AbstractProductListingRoute
     */
    private $decorated;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        AbstractProductListingRoute $decorated,
        SalesChannelRepositoryInterface $productRepository,
        EventDispatcherInterface $eventDispatcher,
        ProductDefinition $definition,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->definition = $definition;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->decorated = $decorated;
        $this->productRepository = $productRepository;
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): ProductListingRouteResponse {
        if (!Utils::isStagingSession($request) && !Utils::isFindologicEnabled($salesChannelContext)) {
            return $this->decorated->load($categoryId, $request, $salesChannelContext);
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new ProductAvailableFilter(
                $salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_ALL
            )
        );

        $criteria->addFilter(
            new EqualsFilter('product.categoriesRo.id', $categoryId)
        );

        $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->definition,
            $salesChannelContext->getContext()
        );

        $this->eventDispatcher->dispatch(
            new ProductListingCriteriaEvent($request, $criteria, $salesChannelContext)
        );

        $result = $this->doSearch($criteria, $salesChannelContext);
        $result = ProductListingResult::createFrom($result);
        $result->addCurrentFilter('navigationId', $categoryId);

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $result, $salesChannelContext)
        );

        return new ProductListingRouteResponse($result);
    }

    protected function doSearch(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        $this->assignPaginationToCriteria($criteria);

        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $context);
        }

        // Shopware can not handle _score sort for listing pages, but since our Findologic navigation always shows
        // top results, we reset it here shortly.
        $preservedSortings = $criteria->getSorting();
        $criteria->resetSorting();

        $result = $this->fetchProducts($criteria, $context);
        foreach ($preservedSortings as $sorting) {
            $criteria->addSorting($sorting);
        }

        return $result;
    }
}
