<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Url;
use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UrlAdapter
{
    protected SalesChannelContext $salesChannelContext;

    protected UrlBuilderService $urlBuilderService;

    public function __construct(
        UrlBuilderService $urlBuilderService,
        SalesChannelContext $salesChannelContext
    ) {
        $urlBuilderService->setSalesChannelContext($salesChannelContext);
        $this->urlBuilderService = $urlBuilderService;
    }

    public function adapt(ProductEntity $product): ?Url
    {
        $rawUrl = $this->urlBuilderService->buildProductUrl($product);

        $url = new Url();
        $url->setValue($rawUrl);

        return $url;
    }
}
