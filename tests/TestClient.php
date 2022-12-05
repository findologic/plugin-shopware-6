<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Request as DomRequest;

class TestClient extends KernelBrowser
{
    private ?SalesChannelContext $salesChannelContext = null;

    protected function filterRequest(DomRequest $request)
    {
        $filteredRequest = parent::filterRequest($request);

        $filteredRequest->attributes->set(
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST,
            !!$this->salesChannelContext
        );
        $filteredRequest->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            $this->salesChannelContext
        );

        return $filteredRequest;
    }

    public function setSalesChannelContext(SalesChannelContext $salesChannelContext): self
    {
        $this->salesChannelContext = $salesChannelContext;

        return $this;
    }
}
