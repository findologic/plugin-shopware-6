<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\Legacy\SalesChannel;

use FINDOLOGIC\FinSearch\Traits\SearchResultHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGatewayInterface as ShopwareProductListingGatewayInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class FindologicProductListingSearchGateway implements ShopwareProductListingGatewayInterface
{
    use SearchResultHelper;

    public const
        TYPE_SEARCH = 0,
        TYPE_NAVIGATION = 1;

    /** @var SalesChannelRepositoryInterface */
    protected $productRepository;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var ProductSearchBuilderInterface|null */
    protected $searchBuilder;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        EventDispatcherInterface $eventDispatcher,
        ?ProductSearchBuilderInterface $searchBuilder = null
    ) {
        $this->productRepository = $repository;
        $this->eventDispatcher = $eventDispatcher;
        $this->searchBuilder = $searchBuilder;
    }

    public static function getInstance(
        int $type,
        SalesChannelRepositoryInterface $repository,
        EventDispatcherInterface $eventDispatcher,
        ?ProductSearchBuilderInterface $searchBuilder = null
    ): ?FindologicProductListingSearchGateway {
        switch ($type) {
            case self::TYPE_SEARCH:
                return new FindologicProductSearchGateway($repository, $eventDispatcher, $searchBuilder);
            case self::TYPE_NAVIGATION:
                return new FindologicProductListingGateway($repository, $eventDispatcher, $searchBuilder);
            default:
                return null;
        }
    }

    abstract public function search(Request $request, SalesChannelContext $salesChannelContext): EntitySearchResult;

    protected function doSearch(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        if (!Utils::isFindologicEnabled($context)) {
            return $this->productRepository->search($criteria, $context);
        }

        $this->assignPaginationToCriteria($criteria);

        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $context);
        }

        return $this->fetchProducts($criteria, $context);
    }
}
