<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Client;

use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClientFactory;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use PHPUnit\Framework\TestCase;

class ServiceConfigClientFactoryTest extends TestCase
{
    use ConfigHelper;

    public function testCreateServiceConfigClient(): void
    {
        $clientFactory = new ServiceConfigClientFactory();
        $serviceConfigClient = $clientFactory->getInstance($this->getShopkey());
        $this->assertInstanceOf(ServiceConfigClient::class, $serviceConfigClient);
    }
}
