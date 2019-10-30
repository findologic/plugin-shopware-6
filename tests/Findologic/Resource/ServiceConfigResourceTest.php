<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Resource;

use FINDOLOGIC\FinSearch\Findologic\Api\ServiceConfig;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClientFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class ServiceConfigResourceTest extends TestCase
{
    use ConfigHelper;

    public function configDataProvider(): array
    {
        return [
            'Direct Integration does not exist in the cache' => [
                'existsInCache' => false,
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ],
            'Direct Integration is enabled and exists in cache' => [
                'existsInCache' => true,
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ],
            'Direct Integration is disabled and exists in cache' => [
                'existsInCache' => true,
                'directionIntegration' => ['enabled' => false],
                'isStagingShop' => false
            ],
            'Staging shop does not exist in the cache' => [
                'existsInCache' => false,
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ],
            'Shop is staging and exists in cache' => [
                'existsInCache' => true,
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => true
            ],
            'Shop is live and exists in cache' => [
                'existsInCache' => true,
                'directionIntegration' => ['enabled' => false],
                'isStagingShop' => false
            ]
        ];
    }

    /**
     * @dataProvider configDataProvider
     *
     * @param bool[] $directIntegration
     *
     * @throws InvalidArgumentException
     */
    public function testIfConfigIsStoredInCache(
        bool $existsInCache,
        array $directIntegration,
        bool $isStagingShop
    ): void {
        $configFromFindologic = $this->getConfig();
        $shopkey = $this->getShopkey();
        $cacheKey = 'finsearch_serviceconfig';

        $serviceConfig = new ServiceConfig();
        $serviceConfig->setFromArray(['directIntegration' => $directIntegration, 'isStagingShop' => $isStagingShop]);

        /** @var ServiceConfigClient|MockObject $serviceConfigClientMock */
        $serviceConfigClientMock = $this->getMockBuilder(ServiceConfigClient::class)
            ->setConstructorArgs([$shopkey])
            ->getMock();
        if ($existsInCache) {
            // Serialize the service config data to mock the cache value
            $serviceConfigFromCache = serialize($serviceConfig);
        } else {
            // Get config data from FINDOLOGIC if the entry in cache does not exist
            $serviceConfigFromCache = null;
            $serviceConfigClientMock->expects($this->exactly(2))
                ->method('get')
                ->willReturn($configFromFindologic);
        }

        $invokeCount = $existsInCache ? $this->never() : $this->exactly(2);

        /** @var ServiceConfigClientFactory|MockObject $findologicClientFactory */
        $findologicClientFactory = $this->getMockBuilder(ServiceConfigClientFactory::class)->getMock();
        $findologicClientFactory->expects($invokeCount)
            ->method('getInstance')
            ->willReturn($serviceConfigClientMock);

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->exactly(2))->method('get')->willReturn($serviceConfigFromCache);

        if (!$existsInCache) {
            $cacheItemMock->expects($this->exactly(2))->method('set')->willReturnSelf();
        } else {
            $cacheItemMock->expects($this->never())->method('set')->willReturnSelf();
        }

        $cachePoolMock->expects($existsInCache ? $this->exactly(2) : $this->exactly(4))
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        if (!$existsInCache) {
            $cachePoolMock->expects($this->exactly(2))->method('save')->with($cacheItemMock);
        }

        $serviceConfigResource = new ServiceConfigResource(
            $cachePoolMock,
            $findologicClientFactory
        );

        $this->assertSame($directIntegration['enabled'], $serviceConfigResource->isDirectIntegration($shopkey));
        $this->assertSame($isStagingShop, $serviceConfigResource->isStaging($shopkey));
    }
}
