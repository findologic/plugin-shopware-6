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
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class SearchNavigationRequestHandler
{
    public function __construct(
        protected readonly ServiceConfigResource $serviceConfigResource,
        protected readonly FindologicRequestFactory $findologicRequestFactory,
        protected readonly Config $config,
        protected readonly ApiConfig $apiConfig,
        protected readonly ApiClient $apiClient,
        protected readonly SortingHandlerService $sortingHandlerService,
        protected ?FilterHandler $filterHandler = null
    ) {
        $this->filterHandler = $filterHandler ?? new FilterHandler();
    }

    abstract public function handleRequest(Request $request, Criteria $criteria, SalesChannelContext $context): void;

    /**
     * Sends a request to the FINDOLOGIC service based on the given event and the responsible request handler.
     *
     * @param int|null $limit limited amount of products
     */
    abstract public function doRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null
    ): Response;

    /**
     * @throws ServiceNotAliveException
     */
    public function sendRequest(SearchNavigationRequest $searchNavigationRequest): Response
    {
        return $this->apiClient->send($searchNavigationRequest);
    }

    protected function setPaginationParams(
        Criteria $criteria,
        SearchNavigationRequest $request,
        ?int $limit,
    ): void {
        $request->setFirst($criteria->getOffset());
        $request->setCount($limit ?? $criteria->getLimit());
    }

    protected function setPagination(
        Criteria $criteria,
        ResponseParser $responseParser,
    ): void {
        $pagination = $responseParser->getPaginationExtension($criteria->getLimit(), $criteria->getOffset());
        $criteria->addExtension('flPagination', $pagination);
    }

    protected function setQueryInfoMessage(SalesChannelContext $context, QueryInfoMessage $queryInfoMessage): void
    {
        $context->getContext()->addExtension('flQueryInfoMessage', $queryInfoMessage);
    }

    protected function setPromotionExtension(
        SalesChannelContext $context,
        ResponseParser $responseParser
    ): void {
        if ($promotion = $responseParser->getPromotionExtension()) {
            $context->getContext()->addExtension('flPromotion', $promotion);
        }
    }

    protected function setUserGroup(
        SalesChannelContext $salesChannelContext,
        SearchNavigationRequest $request
    ): void {
        $group = $salesChannelContext->getCurrentCustomerGroup();
        if (!$group?->getId()) {
            return;
        }

        $request->addUserGroup($group->getId());
    }
}
