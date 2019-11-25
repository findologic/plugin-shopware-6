<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Definitions\OutputAdapter;
use FINDOLOGIC\Api\Exceptions\InvalidParamException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Symfony\Component\HttpFoundation\Request;

class SearchRequestFactory extends FindologicRequestFactory
{
    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidArgumentException
     */
    public function getInstance(Request $request): SearchRequest
    {
        $searchRequest = new SearchRequest();
        $searchRequest->setUserIp($request->getClientIp());
        $searchRequest->setReferer($request->headers->get('referer'));
        $searchRequest->setRevision($this->getPluginVersion());
        $searchRequest->setOutputAdapter(OutputAdapter::XML_21);

        try {
            $searchRequest->setShopUrl($request->getHost());
        } catch (InvalidParamException $e) {
            $searchRequest->setShopUrl('example.org');
        }

        return $searchRequest;
    }
}
