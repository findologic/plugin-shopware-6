<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Promotion as ApiPromotion;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Struct\Pagination;
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
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(ShopwareEvent $event): void
    {
        $originalCriteria = clone $event->getCriteria();
        $request = $event->getRequest();

        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery((string)$request->query->get('search'));
        $this->setPaginationParams($event, $searchRequest);
        $this->addSorting($searchRequest, $event->getCriteria());

        try {
            /** @var Xml21Response $response */
            $response = $this->sendRequest($searchRequest);
        } catch (ServiceNotAliveException $e) {
            $this->assignCriteriaToEvent($event, $originalCriteria);

            return;
        }

        $this->setSmartDidYouMeanExtension($event, $response, $request);
        $criteria = new Criteria($this->parseProductIdsFromResponse($response));
        $this->redirectOnLandingPage($response);
        $this->setPromotionExtension($event, $response);

        $this->setPagination(
            $criteria,
            $originalCriteria->getLimit(),
            $originalCriteria->getOffset(),
            $response->getResults()->getCount()
        );

        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $this->assignCriteriaToEvent($event, $criteria);
    }

    protected function redirectOnLandingPage(Xml21Response $response): void
    {
        $landingPage = $response->getLandingPage();
        if ($landingPage instanceof LandingPage) {
            header('Location:' . $landingPage->getLink());
            exit;
        }
    }

    /**
     * @param ShopwareEvent|ProductSearchCriteriaEvent $event
     */
    protected function setPromotionExtension(ShopwareEvent $event, Xml21Response $response): void
    {
        $promotion = $response->getPromotion();

        if ($promotion instanceof ApiPromotion) {
            $promotion = new Promotion($promotion->getImage(), $promotion->getLink());
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
