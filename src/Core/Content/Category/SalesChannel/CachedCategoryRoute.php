<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Category\SalesChannel;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CachedCategoryRoute extends AbstractCategoryRoute
{
    public function __construct(
        protected readonly AbstractCategoryRoute $decorated,
        protected readonly ServiceConfigResource $serviceConfigResource,
        protected readonly FindologicConfigService $findologicConfigService,
        protected ?Config $config = null
    ) {
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        return $this->decorated;
    }

    public function load(
        string $navigationId,
        Request $request,
        SalesChannelContext $context
    ): CategoryRouteResponse {
        $this->initializePluginState($context, $request);

        return $this->getDecorated()->load($navigationId, $request, $context);
    }

    /**
     * Initialize basic state, to ensure that cached pages render the proper view.
     */
    protected function initializePluginState(
        SalesChannelContext $salesChannelContext,
        Request $request
    ): void {
        if (!$this->config->isInitialized()) {
            $this->config->initializeBySalesChannel($salesChannelContext);
        }

        Utils::shouldHandleRequest(
            $request,
            $salesChannelContext->getContext(),
            $this->serviceConfigResource,
            $this->config,
            true
        );
    }
}
