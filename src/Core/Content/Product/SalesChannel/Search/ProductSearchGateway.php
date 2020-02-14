<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search;

use FINDOLOGIC\FinSearch\Struct\Pagination;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGateway as ShopwareProductSearchGateway;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
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

        // Pagination is handled by FINDOLOGIC.
        $criteria->setLimit(24);
        $criteria->setOffset(0);
        $result = $this->repository->search($criteria, $context);

        /** @var Pagination $pagination */
        $pagination = $criteria->getExtension('flPagination');
        $criteria->setLimit($pagination->getLimit() ?? 24);
        $criteria->setOffset($pagination->getOffset() ?? 0);

        $result = ProductListingResult::createFrom($result);

        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $context),
            ProductEvents::PRODUCT_SEARCH_RESULT
        );

        $result->addCurrentFilter('search', $request->query->get('search'));

        return $result;
    }
}
