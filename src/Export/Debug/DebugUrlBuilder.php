<?php

namespace FINDOLOGIC\FinSearch\Export\Debug;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DebugUrlBuilder
{
    private const PATH_STRUCTURE = '%s/%s?shopkey=%s&productId=%s';
    private const EXPORT_PATH = 'findologic';
    private const DEBUG_PATH = 'findologic/debug';

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var string */
    private $shopkey;

    public function __construct(SalesChannelContext $salesChannelContext, string $shopkey)
    {
        $this->salesChannelContext = $salesChannelContext;
        $this->shopkey = $shopkey;
    }

    public function buildExportUrl(string $productId): string
    {
        return $this->buildUrlByPath(self::EXPORT_PATH, $productId);
    }

    public function buildDebugUrl(string $productId): string
    {
        return $this->buildUrlByPath(self::DEBUG_PATH, $productId);
    }

    private function buildUrlByPath(string $path, string $productId): string
    {
        return sprintf(
            self::PATH_STRUCTURE,
            $this->getShopDomain(),
            $path,
            $this->shopkey,
            $productId
        );
    }

    private function getShopDomain(): string
    {
        if ($domains = $this->salesChannelContext->getSalesChannel()->getDomains()) {
            return $domains->first()->getUrl();
        }

        return '';
    }
}
