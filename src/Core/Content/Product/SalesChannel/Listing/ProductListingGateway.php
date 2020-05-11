<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductListingGateway extends AbstractProductListingRoute
{
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
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        AbstractProductListingRoute $decorated,
        EntityRepositoryInterface $productRepository,
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
        /** @var FindologicEnabled $findologicEnabled */
        $findologicEnabled = $context->getContext()->getExtension('flEnabled');
        $isFindologicEnabled = $findologicEnabled ? $findologicEnabled->getEnabled() : false;

        if (!$isFindologicEnabled) {
            return $this->productRepository->search($criteria, $context->getContext());
        }

        if (empty($criteria->getIds())) {
            // Return an empty response, as Shopware would search for all products if no explicit
            // product ids are submitted.
            return new EntitySearchResult(
                0,
                new EntityCollection(),
                new AggregationResultCollection(),
                $criteria,
                $context->getContext()
            );
        }

        /** @var Pagination $pagination */
        $pagination = $criteria->getExtension('flPagination');
        if ($pagination) {
            // Pagination is handled by FINDOLOGIC.
            $criteria->setLimit(24);
            $criteria->setOffset(0);
        }

        $result = $this->productRepository->search($criteria, $context->getContext());

        return $this->fixResultOrder($result, $criteria);
    }

    /**
     * When search results are fetched from the database, the ordering of the products is based on the
     * database structure, which is not what we want. We manually re-order them by the ID, so the
     * ordering matches the result that the FINDOLOGIC API returned.
     *
     * @param EntitySearchResult $result
     * @param Criteria $criteria
     *
     * @return EntitySearchResult
     */
    private function fixResultOrder(EntitySearchResult $result, Criteria $criteria): EntitySearchResult
    {
        $sortedElements = $this->sortElementsByIdArray($result->getElements(), $criteria->getIds());
        $result->clear();

        foreach ($sortedElements as $element) {
            $result->add($element);
        }

        return $result;
    }

    private function sortElementsByIdArray(array $elements, array $ids): array
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (\is_array($id)) {
                $id = implode('-', $id);
            }

            if (\array_key_exists($id, $elements)) {
                $sorted[$id] = $elements[$id];
            }
        }

        return $sorted;
    }
}
