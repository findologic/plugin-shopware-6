<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Client;

use FINDOLOGIC\FinSearch\Findologic\Client\FindologicClientFactory;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use PHPUnit\Framework\TestCase;

class FindologicClientFactoryTest extends TestCase
{
    use ConfigHelper;

    public function testCreateServiceConfigClient()
    {
        $clientFactory = new FindologicClientFactory();
        $serviceConfigClient = $clientFactory->createServiceConfigClient($this->getShopkey());
        $this->assertInstanceOf(ServiceConfigClient::class, $serviceConfigClient);
    }
}
