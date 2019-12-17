<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Definitions\QueryParameter;
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
        if ($request->get(QueryParameter::FORCE_ORIGINAL_QUERY, false)) {
            $searchRequest->setForceOriginalQuery();
        }

        return $searchRequest;
    }
}
