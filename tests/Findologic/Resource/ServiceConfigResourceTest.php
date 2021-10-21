<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Resource;

use DateTime;
use FINDOLOGIC\FinSearch\Findologic\Api\ServiceConfig;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClientFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ServiceConfigResourceTest extends TestCase
{
    use ConfigHelper;
    use IntegrationTestBehaviour;

    public function cacheConfigDataProvider(): array
    {
        return [
            'Direct Integration is enabled' => [
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ],
            'Direct Integration is disabled' => [
                'directionIntegration' => ['enabled' => false],
                'isStagingShop' => false
            ],
            'Shop is staging' => [
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => true
            ],
            'Shop is live' => [
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ]
        ];
    }

    /**
     * @dataProvider cacheConfigDataProvider
     *
     * @param bool[] $directIntegration
     *
     * @throws InvalidArgumentException
     */
    public function testConfigIsStoredInCache(
        array $directIntegration,
        bool $isStagingShop
    ): void {
        $cacheKey = 'finsearch_serviceconfig_74B87337454200D4D33F80C4663DC5E5';
        $shopkey = $this->getShopkey();
        $serviceConfig = new ServiceConfig();
        $serviceConfig->assign(['directIntegration' => $directIntegration, 'isStagingShop' => $isStagingShop]);

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->exactly(2))->method('get')->willReturn(serialize($serviceConfig));
        $cacheItemMock->expects($this->never())->method('set')->willReturnSelf();

        $cachePoolMock->expects($this->exactly(2))
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);
        $cachePoolMock->expects($this->never())->method('save')->with($cacheItemMock);

        $serviceConfigResource = new ServiceConfigResource(
            $cachePoolMock,
            new ServiceConfigClientFactory()
        );

        $this->assertSame($directIntegration['enabled'], $serviceConfigResource->isDirectIntegration($shopkey));
        $this->assertSame($isStagingShop, $serviceConfigResource->isStaging($shopkey));
    }

    public function findologicConfigDataProvider(): array
    {
        return [
            'Direct Integration is enabled and Shop is live' => [
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false,
                'blocks' => [
                    'cat' => 'Kategorie',
                    'vendor' => 'Hersteller'
                ]
            ],
        ];
    }

    /**
     * @dataProvider findologicConfigDataProvider
     *
     * @param bool[] $directIntegration
     * @param string[] $smartSuggestBlocks
     *
     * @throws InvalidArgumentException
     */
    public function testConfigIsFetchedFromFindologic(
        array $directIntegration,
        bool $isStagingShop,
        array $smartSuggestBlocks
    ): void {
        $cacheKey = 'finsearch_serviceconfig_74B87337454200D4D33F80C4663DC5E5';
        $directIntegrationConfig = $directIntegration ? 'Direct Integration' : 'API';
        $serviceConfig = new ServiceConfig();
        $serviceConfig->assign($this->getConfig());

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(null, serialize($serviceConfig));
        $cacheItemMock->expects($this->once())->method('set')->willReturnSelf();
        $cachePoolMock->expects($this->once())->method('save')->with($cacheItemMock);

        $cachePoolMock->expects($this->exactly(3))
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        // Create a mock and queue one response with the config json file
        $mock = new MockHandler([
            new Response(200, [], $this->getConfig(false))
        ]);
        $handler = HandlerStack::create($mock);

        $client = new Client(['handler' => $handler]);

        $serviceConfigResource = new ServiceConfigResource(
            $cachePoolMock,
            new ServiceConfigClientFactory(),
            $client
        );

        $shopkey = $this->getShopkey();
        $this->assertSame($directIntegration['enabled'], $serviceConfigResource->isDirectIntegration($shopkey));
        $this->assertSame($isStagingShop, $serviceConfigResource->isStaging($shopkey));
        $this->assertArrayHasKey('cat', $smartSuggestBlocks);
        $this->assertArrayHasKey('vendor', $smartSuggestBlocks);
        $this->assertSame('Kategorie', $smartSuggestBlocks['cat']);
        $this->assertSame('Hersteller', $smartSuggestBlocks['vendor']);
    }

    public function expiredTimeProvider(): array
    {
        return [
            'Cache is expired and Direct Integration is true' => [
                'isExpired' => true,
                'directIntegration' => ['enabled' => true],
                'expiredTime' => '-10 days'
            ],
            'Cache is not expired and Direct Integration is false' => [
                'isExpired' => false,
                'directIntegration' => ['enabled' => false],
                'expiredTime' => '+1 days'
            ],
        ];
    }

    /**
     * @dataProvider expiredTimeProvider
     *
     * @throws InvalidArgumentException
     */
    public function testConfigWhenCacheIsExpired(bool $isExpired, array $directIntegration, string $expiredTime): void
    {
        $expiredDateTime = new DateTime();
        $expiredDateTime = $expiredDateTime->modify($expiredTime);
        $directIntegrationConfig = $directIntegration ? 'Direct Integration' : 'API';
        $shopkey = $this->getShopkey();
        $cacheKey = 'finsearch_serviceconfig_74B87337454200D4D33F80C4663DC5E5';

        /** @var ServiceConfig|MockObject $serviceConfig */
        $serviceConfig = $this->getMockBuilder(ServiceConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $serviceConfig->method('getExpireDateTime')->willReturn($expiredDateTime);
        $serviceConfig->method('getDirectIntegration')->willReturn($directIntegration);

        // Serialize the service config data to mock the cache value
        $serviceConfigFromCache = serialize($serviceConfig);

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->once())->method('get')->willReturn($serviceConfigFromCache);

        if ($isExpired) {
            $cacheItemMock->expects($this->once())->method('set')->willReturnSelf();
        } else {
            $cacheItemMock->expects($this->never())->method('set');
        }

        $cachePoolMock->expects($isExpired ? $this->exactly(2) : $this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        if ($isExpired) {
            $cachePoolMock->expects($this->once())->method('save')->with($cacheItemMock);
        } else {
            $cachePoolMock->expects($this->never())->method('save');
        }

        // Create a mock and queue one response with the config json file
        $mock = new MockHandler([
            new Response(200, [], $this->getConfig(false))
        ]);
        $handler = HandlerStack::create($mock);

        $client = new Client(['handler' => $handler]);

        $serviceConfigResource = new ServiceConfigResource(
            $cachePoolMock,
            new ServiceConfigClientFactory(),
            $client
        );

        $this->assertSame($directIntegration['enabled'], $serviceConfigResource->isDirectIntegration($shopkey));
    }

    public function testCachedServiceConfigResourceIsShopkeyAware(): void
    {
        $apiShopkey = 'D5EF9A190C9714C8F1E73EEF0FAFBBC9';
        $diShopkey = '74B87337454200D4D33F80C4663DC5E5';

        $client = new Client(['handler' => $this->getMockHandler([
            new Response(200, [], $this->getConfig(false)),
            new Response(200, [], $this->getConfig(false, 'api_config.json')),
        ])]);

        $serviceConfigResource = new ServiceConfigResource(
            $this->getContainer()->get('serializer.mapping.cache.symfony'),
            new ServiceConfigClientFactory(),
            $client
        );

        $this->assertTrue($serviceConfigResource->isDirectIntegration($diShopkey));
        $this->assertFalse($serviceConfigResource->isDirectIntegration($apiShopkey));
    }

    /**
     * @param Response[] $responses
     * @return HandlerStack
     */
    private function getMockHandler(array $responses): HandlerStack
    {
        $mockHandler = new MockHandler($responses);

        return HandlerStack::create($mockHandler);
    }
}
