<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Storefront\Page\Search;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Search\SearchPage;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoader as ShopwareSearchPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class SearchPageLoader extends ShopwareSearchPageLoader
{
    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var AbstractProductSearchRoute|null
     */
    private $productSearchRoute;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        GenericPageLoader $genericLoader,
        ?AbstractProductSearchRoute $productSearchRoute,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->genericLoader = $genericLoader;
        $this->productSearchRoute = $productSearchRoute;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): SearchPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);
        $page = SearchPage::createFrom($page);

        $result = $this->productSearchRoute
            ->load($request, $salesChannelContext, new Criteria())
            ->getListingResult();

        $page->setListing($result);

        $page->setSearchTerm(
            (string) $request->query->get('search')
        );

        $this->eventDispatcher->dispatch(
            new SearchPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
