<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\QueryInfoMessage;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class SearchNavigationRequestHandler
{
    /**
     * Contains criteria variable keys, which have been added in newer Shopware versions.
     * If they're not set (e.g. an older Shopware version), these values will be set to null by default.
     */
    private const NEW_CRITERIA_VARS = [
        'includes',
        'title',
    ];

    protected ServiceConfigResource $serviceConfigResource;

    protected Config $config;

    protected ApiConfig $apiConfig;

    protected ApiClient $apiClient;

    protected FindologicRequestFactory $findologicRequestFactory;

    protected SortingHandlerService $sortingHandlerService;

    protected FilterHandler $filterHandler;

    public function __construct(
        ServiceConfigResource $serviceConfigResource,
        FindologicRequestFactory $findologicRequestFactory,
        Config $config,
        ApiConfig $apiConfig,
        ApiClient $apiClient,
        SortingHandlerService $sortingHandlerService,
        ?FilterHandler $filterHandler = null
    ) {
        $this->serviceConfigResource = $serviceConfigResource;
        $this->findologicRequestFactory = $findologicRequestFactory;
        $this->config = $config;
        $this->apiConfig = $apiConfig;
        $this->apiClient = $apiClient;
        $this->sortingHandlerService = $sortingHandlerService;
        $this->filterHandler = $filterHandler ?? new FilterHandler();
    }

    abstract public function handleRequest(ShopwareEvent $event): void;

    /**
     * Sends a request to the FINDOLOGIC service based on the given event and the responsible request handler.
     *
     * @param int|null $limit limited amount of products
     */
    abstract public function doRequest(ShopwareEvent $event, ?int $limit = null): Response;

    /**
     * @throws ServiceNotAliveException
     */
    public function sendRequest(SearchNavigationRequest $searchNavigationRequest): Response
    {
        return $this->apiClient->send($searchNavigationRequest);
    }

    /**
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     */
    protected function setPaginationParams(ShopwareEvent $event, SearchNavigationRequest $request, ?int $limit): void
    {
        $request->setFirst($event->getCriteria()->getOffset());
        $request->setCount($limit ?? $event->getCriteria()->getLimit());
    }

    /**
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     */
    protected function assignCriteriaToEvent(ShopwareEvent $event, Criteria $criteria): void
    {
        $vars = $criteria->getVars();

        if (!empty($vars)) {
            $vars['limit'] = $event->getCriteria()->getLimit();

            // Set criteria default vars to allow compatibility with older Shopware versions.
            foreach (self::NEW_CRITERIA_VARS as $varName) {
                if (!array_key_exists($varName, $vars)) {
                    $vars[$varName] = null;
                }
            }
        }

        $event->getCriteria()->assign($vars);
    }

    protected function setPagination(
        Criteria $criteria,
        ResponseParser $responseParser,
        ?int $limit,
        ?int $offset
    ): void {
        $pagination = $responseParser->getPaginationExtension($limit, $offset);
        $criteria->addExtension('flPagination', $pagination);
    }

    protected function setQueryInfoMessage(ShopwareEvent $event, QueryInfoMessage $queryInfoMessage): void
    {
        $event->getContext()->addExtension('flQueryInfoMessage', $queryInfoMessage);
    }

    /**
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     */
    protected function setPromotionExtension(ShopwareEvent $event, ResponseParser $responseParser): void
    {
        if ($promotion = $responseParser->getPromotionExtension()) {
            $event->getContext()->addExtension('flPromotion', $promotion);
        }
    }

    protected function setUserGroup(
        SalesChannelContext $salesChannelContext,
        SearchNavigationRequest $request
    ): void {
        $group = $salesChannelContext->getCurrentCustomerGroup() ?? $salesChannelContext->getFallbackCustomerGroup();
        if (!$group || !$group->getId()) {
            return;
        }

        $request->addUserGroup($group->getId());
    }
}
