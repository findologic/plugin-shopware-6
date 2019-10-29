<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Api;

use DateTime;
use FINDOLOGIC\FinSearch\Findologic\Api\ServiceConfig;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClientFactory;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ServiceConfigTest extends TestCase
{
    use ConfigHelper;

    public function configParameterProvider(): array
    {
        return [
            'Shop is staging with Direct Integration' => [
                'directIntegration' => ['enabled' => true],
                'isStagingShop' => true
            ],
            'Shop is staging without Direct Integration' => [
                'directIntegration' => ['enabled' => false],
                'isStagingShop' => true
            ],
            'Shop is live without Direct Integration' => [
                'directIntegration' => ['enabled' => false],
                'isStagingShop' => false
            ],
            'Shop is live with Direct Integration' => [
                'directIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ],
        ];
    }

    /**
     * @dataProvider configParameterProvider
     */
    public function testServiceConfigAssignment(array $directIntegration, bool $isStagingShop): void
    {
        $config = $this->getConfig();
        $config['directIntegration'] = $directIntegration;
        $config['isStagingShop'] = $isStagingShop;

        // Create a mock and queue one response with the config json file
        $mock = new MockHandler([new Response(200, [], json_encode($config))]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $clientFactory = new ServiceConfigClientFactory();
        $serviceConfigClient = $clientFactory->getInstance($this->getShopkey(), $client);

        $serviceConfig = new ServiceConfig();
        $serviceConfig->setFromArray($serviceConfigClient->get());

        $expectedExpiration = new DateTime('+1 day');
        $expirationDate = $serviceConfig->getExpireDateTime();

        $this->assertSame($directIntegration, $serviceConfig->getDirectIntegration());
        $this->assertEquals($isStagingShop, $serviceConfig->getIsStagingShop());
        $this->assertEquals($expectedExpiration->format('Y-m-d'), $expirationDate->format('Y-m-d'));
    }
}
