<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Url;
use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UrlAdapter
{
    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var UrlBuilderService */
    protected $urlBuilderService;

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

        if ($rawUrl === null) {
            return null;
        }

        $url = new Url();
        $url->setValue($rawUrl);

        return $url;
    }
}
