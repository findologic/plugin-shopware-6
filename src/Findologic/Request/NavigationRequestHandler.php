<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(NestedEvent $event)
    {
        /** @var ProductListingCriteriaEvent $event */
        parent::handleRequest($event);

        /** @var Request $request */
        $request = $event->getRequest();

        // We simply return if the current page is not a category page
        if (!$request->get('cat')) {
            return;
        }

        /** @var NavigationRequest $navigationRequest */
        $navigationRequest = $this->findologicRequestFactory->getInstance($request);

        try {
            $response = $this->apiClient->send($navigationRequest);
        } catch (ServiceNotAliveException $e) {
            return;
        }

        $productIds = array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $response->getProducts()
        );

        $cleanCriteria = new Criteria($productIds);
        $event->getCriteria()->assign($cleanCriteria->getVars());
    }
}
