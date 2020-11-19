<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber
    as ShopwareProductListingFeaturesSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    /** @var ShopwareProductListingFeaturesSubscriber */
    protected $decorated;

    /** @var FindologicSearchService */
    protected $findologicSearchService;

    public function __construct(
        ShopwareProductListingFeaturesSubscriber $decorated,
        FindologicSearchService $findologicSearchService
    ) {
        $this->decorated = $decorated;
        $this->findologicSearchService = $findologicSearchService;
    }

    public static function getSubscribedEvents(): array
    {
        return ShopwareProductListingFeaturesSubscriber::getSubscribedEvents();
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        $this->decorated->handleResult($event);
        $this->findologicSearchService->handleResult($event);
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        $limit = $event->getCriteria()->getLimit();
        $this->decorated->handleListingRequest($event);

        $this->findologicSearchService->doNavigation($event, $limit);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        $limit = $event->getCriteria()->getLimit();
        $this->decorated->handleSearchRequest($event);

        $this->findologicSearchService->doSearch($event, $limit);
    }

    public function __call($method, $args)
    {
        if (!method_exists($this->decorated, $method)) {
            return;
        }

        return $this->decorated->{$method}(...$args);
    }
}
