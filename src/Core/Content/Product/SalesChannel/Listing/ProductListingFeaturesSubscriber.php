<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber as ShopwareProductListingFeaturesSubscriber;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\TestAggregation;
use Symfony\Component\HttpFoundation\Request;

class ProductListingFeaturesSubscriber extends ShopwareProductListingFeaturesSubscriber
{
    /** @var string FINDOLOGIC default sort for categories */
    public const DEFAULT_SORT = 'score';

    /** @var ProductListingSortingRegistry */
    private $sortingRegistry;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $optionRepository,
        ProductListingSortingRegistry $sortingRegistry
    ) {
        $this->sortingRegistry = $sortingRegistry;
        parent::__construct($connection, $optionRepository, $sortingRegistry);
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        parent::handleResult($event);

        $defaultSort = $event instanceof ProductSearchResultEvent ? self::DEFAULT_SEARCH_SORT : self::DEFAULT_SORT;
        $currentSorting = $this->getCurrentSorting($event->getRequest(), $defaultSort);

        $event->getResult()->setSorting($currentSorting);
        $this->sortingRegistry->add(
            new ProductListingSorting('score', 'filter.sortByScore', ['_score' => 'desc'])
        );
        $sortings = $this->sortingRegistry->getSortings();
        /** @var ProductListingSorting $sorting */
        foreach ($sortings as $sorting) {
            $sorting->setActive($sorting->getKey() === $currentSorting);
        }

        $event->getResult()->setSortings($sortings);
    }

    private function getCurrentSorting(Request $request, string $default): ?string
    {
        $key = $request->get('sort', $default);

        if (!$key) {
            return null;
        }

        if ($this->sortingRegistry->has($key)) {
            return $key;
        }

        return $default;
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        parent::handleListingRequest($event);
        $criteria = $event->getCriteria();
        $request = $event->getRequest();

        $criteria->addAggregation(
            new FilterAggregation(
                'test-filter',
                new MaxAggregation('test', 'product.shippingFree'),
                [new EqualsFilter('product.shippingFree', true)]
            )
        );

        $filtered = $request->get('shipping-free');

        if (!$filtered) {
            return;
        }

        $criteria->addPostFilter(new EqualsFilter('product.shippingFree', true));

    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        parent::handleSearchRequest($event);
        // TODO Add filters to criteria from FINDOLOGIC Response
    }
}
