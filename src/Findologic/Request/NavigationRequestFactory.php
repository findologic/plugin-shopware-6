<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

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
    public function getInstance(Request $request): NavigationRequest
    {
        $navigationRequest = new NavigationRequest();
        $this->setDefaults($request, $navigationRequest);

        return $navigationRequest;
    }
}
