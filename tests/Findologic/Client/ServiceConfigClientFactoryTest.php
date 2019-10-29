<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Client;

use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClientFactory;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use PHPUnit\Framework\TestCase;

class ServiceConfigClientFactoryTest extends TestCase
{
    use ConfigHelper;

    public function testCreateServiceConfigClient()
    {
        $clientFactory = new ServiceConfigClientFactory();
        $serviceConfigClient = $clientFactory->getInstance($this->getShopkey());
        $this->assertInstanceOf(ServiceConfigClient::class, $serviceConfigClient);
    }
}
