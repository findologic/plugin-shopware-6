<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Json10\Json10Response;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery((string)$request->query->get('search'));
        $this->sortingHandlerService->handle($searchRequest, $criteria);

        try {
            /** @var Json10Response $response */
            $response = $this->doRequest($request, $criteria, $context);
            $responseParser = ResponseParser::getInstance(
                $response,
                $this->serviceConfigResource,
                $this->config
            );
        } catch (ServiceNotAliveException) {
            return;
        }

        if ($responseParser->getLandingPageExtension()) {
            $this->handleLandingPage($responseParser, $context);

            return;
        }

        $context->getContext()->addExtension(
            'flSmartDidYouMean',
            $responseParser->getSmartDidYouMeanExtension($request)
        );

        $criteria->setIds(
            $responseParser->getProductIds() === [] ? null : $responseParser->getProductIds()
        );

        $this->setPromotionExtension($context, $responseParser);
        $this->setPagination($criteria, $responseParser);
        $this->setQueryInfoMessage($context, $responseParser->getQueryInfoMessage($request, $context));
    }

    /**
     * @throws ServiceNotAliveException
     */
    public function doRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null
    ): Response {
        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery((string)$request->query->get('search'));
        $this->setUserGroup($context, $searchRequest);
        $this->setPaginationParams($criteria, $searchRequest, $limit);
        $this->sortingHandlerService->handle($searchRequest, $criteria);
        if ($criteria->hasExtension('flFilters')) {
            $this->filterHandler->handleFilters($request, $criteria, $searchRequest);
        }

        return $this->sendRequest($searchRequest);
    }

    protected function handleLandingPage(ResponseParser $responseParser, SalesChannelContext $context): void
    {
        $context->getContext()->addExtension(
            'flLandingPage',
            $responseParser->getLandingPageExtension()
        );
    }
}
