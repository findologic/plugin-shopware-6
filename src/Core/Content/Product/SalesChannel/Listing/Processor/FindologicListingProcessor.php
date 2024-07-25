<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\Processor;

use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class FindologicListingProcessor extends AbstractListingProcessor
{
    public function __construct(
        protected readonly FindologicSearchService $findologicSearchService,
        private readonly ServiceConfigResource $serviceConfigResource,
        FindologicConfigService $findologicConfigService,
        private ?Config $config = null,
    ) {
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$this->config->isInitialized()) {
            $this->config->initializeBySalesChannel($context);
        }

        if (Utils::shouldHandleRequest($request, $context->getContext(), $this->serviceConfigResource, $this->config)) {
            if (Utils::isSearchPage($request)) {
                $this->findologicSearchService->doSearch($request, $criteria, $context);
            } elseif (Utils::isNavigationPage($request)) {
                $this->findologicSearchService->doNavigation($request, $criteria, $context);
            }
        }
    }
}
