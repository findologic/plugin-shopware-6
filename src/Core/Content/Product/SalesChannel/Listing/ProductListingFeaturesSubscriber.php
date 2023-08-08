<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber
    as ShopwareProductListingFeaturesSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:6.0.0 - Subscriber will be removed, because the parent class is deprecated since SW 6.5.3.0
 */
class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    protected bool $isListingRequestHandled = false;

    public function __construct(
        protected readonly ShopwareProductListingFeaturesSubscriber $decorated,
        protected readonly FindologicSearchService $findologicSearchService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => [
                ['handleListingRequest', 100],
            ],
            ProductSuggestCriteriaEvent::class => 'prepare',
            ProductSearchCriteriaEvent::class => [
                ['handleSearchRequest', 100],
            ],
            ProductListingResultEvent::class => 'process',
            ProductSearchResultEvent::class => 'process',
        ];
    }

    public function prepare(ProductListingCriteriaEvent $event): void
    {
        $this->decorated->prepare($event);
    }

    public function process(ProductListingResultEvent $event): void
    {
        $this->decorated->process($event);
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        $limit = $event->getCriteria()->getLimit();
        $this->decorated->prepare($event);

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
        $this->decorated->prepare($event);

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
