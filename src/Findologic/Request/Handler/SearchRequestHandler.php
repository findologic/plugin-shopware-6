<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Promotion as ApiPromotion;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(ShopwareEvent $event): void
    {
        $originalCriteria = clone $event->getCriteria();
        $request = $event->getRequest();

        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery($request->query->get('search'));

        try {
            $response = $this->sendRequest($searchRequest);
            $cleanCriteria = new Criteria($this->parseProductIdsFromResponse($response));

            $landingPage = $response->getLandingPage();
            if ($landingPage instanceof LandingPage) {
                header('Location:' . $landingPage->getLink());
                exit;
            }

            $promotion = $response->getPromotion();

            if ($promotion instanceof ApiPromotion) {
                $promotion = new Promotion($promotion->getImage(), $promotion->getLink());
                $event->getContext()->addExtension('flPromotion', $promotion);
            }

            $this->assignCriteriaToEvent($event, $cleanCriteria);
        } catch (ServiceNotAliveException $e) {
            $this->assignCriteriaToEvent($event, $originalCriteria);
        }
    }
}
