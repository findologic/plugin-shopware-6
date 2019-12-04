<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;

class SearchRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(NestedEvent $event)
    {
        /** @var ProductSearchCriteriaEvent $event */
        parent::handleRequest($event);

        $originalCriteria = clone $event->getCriteria();
        $request = $event->getRequest();

        /** @var SearchRequest $searchRequest */
        $searchRequest = $this->findologicRequestFactory->getInstance($request);
        $searchRequest->setQuery($request->query->get('search'));

        try {
            $response = $this->apiClient->send($searchRequest);
        } catch (ServiceNotAliveException $e) {
            $event->getCriteria()->assign($originalCriteria->getVars());

            return;
        }

        $productIds = array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $response->getProducts()
        );

        if ($response->getPromotion() !== null) {
            $promotion = new Promotion($response->getPromotion()->getImage(), $response->getPromotion()->getLink());
            $event->getContext()->addExtension('flPromotion', $promotion);
        }

        $cleanCriteria = new Criteria($productIds);
        $event->getCriteria()->assign($cleanCriteria->getVars());
    }
}
