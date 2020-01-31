<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Definitions\QueryParameter;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
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
        $this->setDefaults($request, $searchRequest);

        return $searchRequest;
    }

    protected function setDefaults(
        Request $request,
        SearchNavigationRequest $searchNavigationRequest
    ): SearchNavigationRequest {
        parent::setDefaults($request, $searchNavigationRequest);

        if ($request->get(QueryParameter::FORCE_ORIGINAL_QUERY, false)) {
            $searchNavigationRequest->setForceOriginalQuery();
        }

        return $searchNavigationRequest;
    }
}
