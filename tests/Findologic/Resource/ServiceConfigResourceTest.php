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

class ServiceConfigResourceTest extends TestCase
{
    use ConfigHelper;

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
        $shopkey = $this->getShopkey();
        $cacheKey = 'finsearch_serviceconfig';

        $serviceConfig = new ServiceConfig();
        $serviceConfig->assign(['directIntegration' => $directIntegration, 'isStagingShop' => $isStagingShop]);

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects(static::exactly(2))->method('get')->willReturn(serialize($serviceConfig));
        $cacheItemMock->expects(static::never())->method('set')->willReturnSelf();

        $cachePoolMock->expects(static::exactly(2))
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);
        $cachePoolMock->expects(static::never())->method('save')->with($cacheItemMock);

        $serviceConfigResource = new ServiceConfigResource(
            $cachePoolMock,
            new ServiceConfigClientFactory()
        );

        static::assertSame($directIntegration['enabled'], $serviceConfigResource->isDirectIntegration($shopkey));
        static::assertSame($isStagingShop, $serviceConfigResource->isStaging($shopkey));
    }

    public function findologicConfigDataProvider(): array
    {
        return [
            'Direct Integration is enabled and Shop is live' => [
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ],
        ];
    }

    /**
     * @dataProvider findologicConfigDataProvider
     *
     * @param bool[] $directIntegration
     *
     * @throws InvalidArgumentException
     */
    public function testConfigIsFetchedFromFindologic(
        array $directIntegration,
        bool $isStagingShop
    ): void {
        $shopkey = $this->getShopkey();
        $cacheKey = 'finsearch_serviceconfig';

        $serviceConfig = new ServiceConfig();
        $serviceConfig->assign($this->getConfig());

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects(static::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(null, serialize($serviceConfig));
        $cacheItemMock->expects(static::once())->method('set')->willReturnSelf();
        $cachePoolMock->expects(static::once())->method('save')->with($cacheItemMock);

        $cachePoolMock->expects(static::exactly(3))
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

        static::assertSame($directIntegration['enabled'], $serviceConfigResource->isDirectIntegration($shopkey));
        static::assertSame($isStagingShop, $serviceConfigResource->isStaging($shopkey));
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

        $shopkey = $this->getShopkey();
        $cacheKey = 'finsearch_serviceconfig';

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
        $cacheItemMock->expects(static::once())->method('get')->willReturn($serviceConfigFromCache);

        if ($isExpired) {
            $cacheItemMock->expects(static::once())->method('set')->willReturnSelf();
        } else {
            $cacheItemMock->expects(static::never())->method('set');
        }

        $cachePoolMock->expects($isExpired ? static::exactly(2) : static::once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        if ($isExpired) {
            $cachePoolMock->expects(static::once())->method('save')->with($cacheItemMock);
        } else {
            $cachePoolMock->expects(static::never())->method('save');
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

        static::assertSame($directIntegration['enabled'], $serviceConfigResource->isDirectIntegration($shopkey));
    }
}
