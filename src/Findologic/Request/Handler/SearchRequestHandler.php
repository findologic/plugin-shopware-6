<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Promotion as ApiPromotion;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(ShopwareEvent $event): void
    {
        $originalCriteria = clone $event->getCriteria();

        /** @var Request $request */
        $request = $event->getRequest();

        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery($request->query->get('search'));

        try {
            /** @var Xml21Response $response */
            $response = $this->sendRequest($searchRequest);
            $event->getContext()->addExtension(
                'flSmartDidYouMean',
                new SmartDidYouMean($response->getQuery(), $request->getRequestUri())
            );
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
