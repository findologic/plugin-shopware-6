<?php

namespace FINDOLOGIC\FinSearch\Tests\Traits;

use FINDOLOGIC\FinSearch\Tests\TestClient;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait WithTestClient
{
    use IntegrationTestBehaviour;

    public function getTestClient(?SalesChannelContext $salesChannelContext = null): TestClient
    {
        $client = new TestClient($this->getKernel());
        if ($salesChannelContext) {
            $client->setSalesChannelContext($salesChannelContext);
        }

        return $client;
    }
}
