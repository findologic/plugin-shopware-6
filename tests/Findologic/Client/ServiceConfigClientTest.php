<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Client;

use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ServiceConfigClientTest extends TestCase
{
    use ConfigHelper;

    public function responseDataProvider(): array
    {
        return [
            'Response is successful' => [200, null],
            'Response is not successful' => [404, ClientException::class],
        ];
    }

    /**
     * @dataProvider responseDataProvider
     */
    public function testConfigUrlAndValues(int $responseCode, ?string $exception): void
    {
        $shopkey = $this->getShopkey();

        if ($responseCode === 200) {
            $body = $this->getConfig(false);
        } else {
            $this->expectException($exception);
            $body = null;
        }
        // Create a mock and queue one response with the config json file
        $mock = new MockHandler([new Response($responseCode, [], $body)]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $serviceConfigClient = new ServiceConfigClient($shopkey, $client);
        $result = $serviceConfigClient->get();

        $this->assertIsArray($result);

        // Make sure that the config returned is the same as the one we expect
        $this->assertSame($this->getConfig(), $result);
    }
}
