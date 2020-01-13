<?php

namespace FINDOLOGIC\FinSearch\Storefront\Page\Search;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
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
     * @var ProductSearchGatewayInterface
     */
    private $searchGateway;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        GenericPageLoader $genericLoader,
        ProductSearchGatewayInterface $searchGateway,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($genericLoader, $searchGateway, $eventDispatcher);
        $this->genericLoader = $genericLoader;
        $this->searchGateway = $searchGateway;
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

        $result = $this->searchGateway->search($request, $salesChannelContext);

        $page->setListing($result);
        $page->setSearchResult(StorefrontSearchResult::createFrom($result));

        $page->setSearchTerm(
            (string) $request->query->get('search')
        );

        $this->eventDispatcher->dispatch(
            new SearchPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
