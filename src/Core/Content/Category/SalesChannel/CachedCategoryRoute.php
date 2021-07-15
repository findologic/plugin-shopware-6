<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Category\SalesChannel;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CachedCategoryRoute extends AbstractCategoryRoute
{
    /** @var AbstractCategoryRoute */
    protected $decorated;

    /** @var ServiceConfigResource */
    protected $serviceConfigResource;

    /** @var Config */
    protected $config;

    public function __construct(
        AbstractCategoryRoute $decorated,
        ServiceConfigResource $serviceConfigResource,
        FindologicConfigService $findologicConfigService,
        ?Config $config = null
    ) {
        $this->decorated = $decorated;
        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        return $this->decorated;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): CategoryRouteResponse {
        $this->initializePluginState($salesChannelContext, $request);

        return $this->getDecorated()->load($categoryId, $request, $salesChannelContext);
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
