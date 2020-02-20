<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Promotion as ApiPromotion;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Filter\CustomFilters;
use FINDOLOGIC\FinSearch\Struct\Filter\FilterValue;
use FINDOLOGIC\FinSearch\Struct\Filter\LabelTextFilter;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InconsistentCriteriaIdsException
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     */
    public function handleRequest(ShopwareEvent $event): void
    {
        if (!$event->getContext()->getExtension('flEnabled')->getEnabled()) {
            return;
        }

        $originalCriteria = clone $event->getCriteria();

        try {
            $response = $this->doRequest($event);
            $responseParser = ResponseParser::getInstance($response);
        } catch (ServiceNotAliveException $e) {
            $this->assignCriteriaToEvent($event, $originalCriteria);
            return;
        }

        $this->handleFilters($response, $event->getCriteria());

        $event->getContext()->addExtension(
            'flSmartDidYouMean',
            $responseParser->getSmartDidYouMeanExtension($event->getRequest())
        );

        $criteria = new Criteria($responseParser->getProductIds());
        $criteria->addExtensions($event->getCriteria()->getExtensions());

        $this->redirectOnLandingPage($responseParser);
        $this->setPromotionExtension($event, $responseParser);

        $criteria->setLimit($originalCriteria->getLimit());
        $criteria->setOffset($originalCriteria->getOffset());
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $this->assignCriteriaToEvent($event, $criteria);
    }

    /**
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     * @param int|null $limit
     * @return Response|null
     * @throws ServiceNotAliveException
     */
    public function doRequest(ShopwareEvent $event, ?int $limit = null): ?Response
    {
        if (!$event->getContext()->getExtension('flEnabled')->getEnabled()) {
            return null;
        }

        $request = $event->getRequest();

        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery((string)$request->query->get('search'));
        $this->setPaginationParams($event, $searchRequest, $limit);

        return $this->sendRequest($searchRequest);
    }

    protected function redirectOnLandingPage(ResponseParser $responseParser): void
    {
        if ($landingPageUri = $responseParser->getLandingPageUri()) {
            header('Location:' . $landingPageUri);
            exit;
        }
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

    protected function setSmartDidYouMeanExtension(
        ShopwareEvent $event,
        Xml21Response $response,
        Request $request
    ): void {
        $event->getContext()->addExtension(
            'flSmartDidYouMean',
            new SmartDidYouMean($response->getQuery(), $request->getRequestUri())
        );
    }
}
