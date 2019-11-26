<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Definitions\OutputAdapter;
use FINDOLOGIC\Api\Exceptions\InvalidParamException;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestFactory extends FindologicRequestFactory
{
    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function getInstance(Request $request, string $categoryPath): NavigationRequest
    {
        $navigationRequest = new NavigationRequest();
        $navigationRequest->setUserIp($request->getClientIp());
        $navigationRequest->setReferer($request->headers->get('referer'));
        $navigationRequest->setRevision($this->getPluginVersion());
        $navigationRequest->setOutputAdapter(OutputAdapter::XML_21);
        $navigationRequest->setSelected('catFilter', $categoryPath);

        try {
            $navigationRequest->setShopUrl($request->getHost());
        } catch (InvalidParamException $e) {
            $navigationRequest->setShopUrl('example.org');
        }

        return $navigationRequest;
    }
}
