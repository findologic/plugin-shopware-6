<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(ShopwareEvent $event): void
    {
        $request = $event->getRequest();

        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery((string)$request->query->get('search'));
        $originalCriteria = clone $event->getCriteria();
        $this->addSorting($searchRequest, $event->getCriteria());

        try {
            /** @var Xml21Response $response */
            $response = $this->doRequest($event);
            $responseParser = ResponseParser::getInstance($response);
        } catch (ServiceNotAliveException $e) {
            $this->assignCriteriaToEvent($event, $originalCriteria);

            return;
        }

        if ($responseParser->getLandingPageExtension()) {
            $this->handleLandingPage($responseParser, $event);

            return;
        }

        $event->getContext()->addExtension(
            'flSmartDidYouMean',
            $responseParser->getSmartDidYouMeanExtension($event->getRequest())
        );

        $criteria = new Criteria($responseParser->getProductIds());
        $criteria->addExtensions($event->getCriteria()->getExtensions());

        $this->setPromotionExtension($event, $responseParser);

        $this->setPagination(
            $criteria,
            $responseParser,
            $originalCriteria->getLimit(),
            $originalCriteria->getOffset()
        );

        $this->setQueryInfoMessage($event, $responseParser->getQueryInfoMessage($event));
        $this->assignCriteriaToEvent($event, $criteria);
    }

    /**
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     *
     * @throws ServiceNotAliveException
     */
    public function doRequest(ShopwareEvent $event, ?int $limit = null): Response
    {
        $request = $event->getRequest();

        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery((string)$request->query->get('search'));
        $this->setPaginationParams($event, $searchRequest, $limit);
        $this->addSorting($searchRequest, $event->getCriteria());
        if ($event->getCriteria()->hasExtension('flFilters')) {
            $this->filterHandler->handleFilters($event, $searchRequest);
        }

        return $this->sendRequest($searchRequest);
    }

    protected function handleLandingPage(ResponseParser $responseParser, ShopwareEvent $event): void
    {
        $event->getContext()->addExtension(
            'flLandingPage',
            $responseParser->getLandingPageExtension()
        );
    }
}
