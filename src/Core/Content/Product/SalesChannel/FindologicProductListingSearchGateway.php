<?php

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel;

use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingGateway;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search\ProductSearchGateway;
use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGatewayInterface as
    ShopwareProductListingGatewayInterface;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGatewayInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class FindologicProductListingSearchGateway implements ShopwareProductListingGatewayInterface
{
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

    public abstract function search(Request $request, SalesChannelContext $salesChannelContext): EntitySearchResult;

    protected function doSearch(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        /** @var FindologicEnabled $findologicEnabled */
        $findologicEnabled = $context->getContext()->getExtension('flEnabled');
        $isFindologicEnabled = $findologicEnabled ? $findologicEnabled->getEnabled() : false;

        if (!$isFindologicEnabled) {
            return $this->productRepository->search($criteria, $context);
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
        $result = $this->productRepository->search($criteria, $context);

        return $this->fixResultOrder($result, $criteria);
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
