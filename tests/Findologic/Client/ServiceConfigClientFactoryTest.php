<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Client;

use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClientFactory;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
use FINDOLOGIC\FinSearch\Tests\Traits\ConfigHelperTrait;
use PHPUnit\Framework\TestCase;

class ServiceConfigClientFactoryTest extends TestCase
{
    use ConfigHelperTrait;

    public function testCreateServiceConfigClient()
    {
        $clientFactory = new ServiceConfigClientFactory();
        $serviceConfigClient = $clientFactory->getInstance($this->getShopkey());
        $this->assertInstanceOf(ServiceConfigClient::class, $serviceConfigClient);
    }
}
