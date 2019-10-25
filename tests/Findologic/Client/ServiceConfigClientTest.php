<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Client;

use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ServiceConfigClientTest extends TestCase
{
    use ConfigHelper;

    public function testConfigUrlAndValues(): void
    {
        $shopkey = $this->getShopkey();

        // Create a mock and queue one response with the config json file
        $mock = new MockHandler([new Response(200, [], $this->getConfig(false))]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $serviceConfigClient = new ServiceConfigClient($shopkey, $client);
        $result = $serviceConfigClient->get();

        $this->assertIsArray($result);

        // Make sure that the config returned is the same as the one we expect
        $this->assertSame($this->getConfig(), $result);
    }
}
