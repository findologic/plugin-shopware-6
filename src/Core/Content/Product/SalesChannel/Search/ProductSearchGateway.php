<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search;

use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGateway as ShopwareProductSearchGateway;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductSearchGateway extends ShopwareProductSearchGateway
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductSearchBuilderInterface
     */
    private $searchBuilder;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        ProductSearchBuilderInterface $searchBuilder,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($repository, $searchBuilder, $eventDispatcher);
        $this->repository = $repository;
        $this->eventDispatcher = $eventDispatcher;
        $this->searchBuilder = $searchBuilder;
    }

    public function search(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new ProductAvailableFilter(
                $context->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $this->searchBuilder->build($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductSearchCriteriaEvent($request, $criteria, $context),
            ProductEvents::PRODUCT_SEARCH_CRITERIA
        );

        $result = $this->doSearch($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $context),
            ProductEvents::PRODUCT_SEARCH_RESULT
        );

        $result->addCurrentFilter('search', $request->query->get('search'));

        return $result;
    }

    protected function sortElementsByIdArray(array $elements, array $ids): array
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

    /**
     * When search results are fetched from the database, the ordering of the products is based on the
     * database structure, which is not what we want. We manually re-order them by the ID, so the
     * ordering matches the result that the FINDOLOGIC API returned.
     *
     * @param EntitySearchResult $result
     * @param Criteria $criteria
     * @return EntitySearchResult
     */
    protected function fixResultOrder(EntitySearchResult $result, Criteria $criteria): EntitySearchResult
    {
        $sortedElements = $this->sortElementsByIdArray($result->getElements(), $criteria->getIds());
        $result->clear();

        foreach ($sortedElements as $element) {
            $result->add($element);
        }

        return $result;
    }

    protected function doSearch(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        /** @var FindologicEnabled $findologicEnabled */
        $findologicEnabled = $context->getContext()->getExtension('flEnabled');
        $isFindologicEnabled = $findologicEnabled ? $findologicEnabled->getEnabled() : false;

        if (!$isFindologicEnabled) {
            return $this->repository->search($criteria, $context);
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

        // Pagination is handled by FINDOLOGIC.
        $criteria->setLimit(24);
        $criteria->setOffset(0);

        /** @var Pagination $pagination */
        $pagination = $criteria->getExtension('flPagination');
        if ($pagination) {
            $criteria->setLimit($pagination->getLimit() ?? 24);
            $criteria->setOffset($pagination->getOffset() ?? 0);
        }

        $result = $this->repository->search($criteria, $context);
        return $this->fixResultOrder($result, $criteria);
    }
}
