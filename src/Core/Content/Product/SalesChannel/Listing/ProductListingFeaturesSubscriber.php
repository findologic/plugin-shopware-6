<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
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

    /** @var bool */
    protected $isListingRequestHandled = false;

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

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        $limit = $event->getCriteria()->getLimit();
        $this->decorated->handleListingRequest($event);

        if (!$this->shouldHandleListingRequest()) {
            return;
        }

        $limitOverride = $limit ?? $event->getCriteria()->getLimit();
        $this->findologicSearchService->doNavigation($event, $limitOverride);

        $this->isListingRequestHandled = true;
    }

    /**
     * The ProductListingCriteriaEvent is triggered twice on initial navigation page request. To avoid
     * multiple requests to Findologic, the event must only be handled on first dispatch.
     */
    protected function shouldHandleListingRequest(): bool
    {
        return !$this->isListingRequestHandled;
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        $limit = $event->getCriteria()->getLimit();
        $this->decorated->handleSearchRequest($event);

        $limitOverride = $limit ?? $event->getCriteria()->getLimit();

        $this->findologicSearchService->doSearch($event, $limitOverride);
    }

    public function __call($method, $args)
    {
        if (!method_exists($this->decorated, $method)) {
            return;
        }

        return $this->decorated->{$method}(...$args);
    }
}
