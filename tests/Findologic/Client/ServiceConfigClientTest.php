<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Client;

use Exception;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
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
            'Response is successful' => [200, $this->getConfig(false)],
            'Response is not successful' => [404],
        ];
    }

    /**
     * @dataProvider responseDataProvider
     */
    public function testConfigUrlAndValues(int $responseCode, ?string $body = null): void
    {
        $shopkey = $this->getShopkey();

        // Create a mock and queue one response with the config json file
        $mock = new MockHandler([new Response($responseCode, [], $body)]);
        $handler = HandlerStack::create($mock);

        $client = new Client(['handler' => $handler]);
        $serviceConfigClient = new ServiceConfigClient($shopkey, $client);

        try {
            $result = $serviceConfigClient->get();
            $this->assertIsArray($result);

            // Make sure that the config returned is the same as the one we expect
            $this->assertSame($this->getConfig(), $result);
        } catch (ClientException $e) {
            $this->assertSame(
                sprintf(
                    'Client error: `GET config/%s/config.json` resulted in a `404 Not Found` response',
                    $shopkey
                ),
                $e->getMessage()
            );
        } catch (Exception $e) {
            $this->fail('Failed due to unknown exception: ' . $e->getMessage());
        }
    }
}
