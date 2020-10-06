<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\CompatibilityLayer\Shopware61\Core\Content\Product\SalesChannel\Search;

use FINDOLOGIC\FinSearch\CompatibilityLayer\Shopware61\Core\Content\Product\SalesChannel\FindologicProductListingGateway
    as DecoratedProductListingGateway;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGateway
    as ShopwareProductSearchGateway;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
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
        $findologicProductListingSearchGateway = DecoratedProductListingGateway::getInstance(
            DecoratedProductListingGateway::TYPE_SEARCH,
            $this->repository,
            $this->eventDispatcher,
            $this->searchBuilder
        );

        return $findologicProductListingSearchGateway->search($request, $context);
    }
}
