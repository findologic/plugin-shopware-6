<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Resource;

use FINDOLOGIC\FinSearch\Findologic\Api\ServiceConfig;
use FINDOLOGIC\FinSearch\Findologic\Client\FindologicClientFactory;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClient;
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

    public function configDataProvider()
    {
        return [
            'Direct Integration does not exist in the cache' => [
                'directionIntegration' => ['enabled' => false],
                'isStagingShop' => false
            ],
            'Direct Integration is enabled and exists in cache' => [
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ],
            'Direct Integration is disabled and exists in cache' => [
                'directionIntegration' => ['enabled' => false],
                'isStagingShop' => false
            ],
            'Staging shop does not exist in the cache' => [
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => false
            ],
            'Shop is staging and exists in cache' => [
                'directionIntegration' => ['enabled' => true],
                'isStagingShop' => true
            ],
            'Shop is live and exists in cache' => [
                'directionIntegration' => ['enabled' => false],
                'isStagingShop' => false
            ]
        ];
    }

    /**
     * @dataProvider configDataProvider
     * @throws InvalidArgumentException
     */
    public function testIfConfigIsStoredInCache(array $directIntegration, bool $isStagingShop)
    {
        // Config from FDL
        $config = $this->getConfig();
        $shopkey = $this->getShopkey();

        $serviceConfig = new ServiceConfig();
        $serviceConfig->setFromArray(['directIntegration' => $directIntegration, 'isStagingShop' => $isStagingShop]);

        /** @var ServiceConfigClient|MockObject $serviceConfigClientMock */
        $serviceConfigClientMock = $this->getMockBuilder(ServiceConfigClient::class)
            ->setConstructorArgs([$shopkey])
            ->getMock();
        $serviceConfigClientMock->expects($this->once())->method('get')->willReturn($config);

        /** @var FindologicClientFactory|MockObject $findologicClientFactory */
        $findologicClientFactory = $this->getMockBuilder(FindologicClientFactory::class)->getMock();
        $findologicClientFactory->expects($this->once())
            ->method('createServiceConfigClient')
            ->willReturn($serviceConfigClientMock);

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->method('get')->willReturn(serialize($serviceConfig));

        $cachePoolMock->method('getItem')->with('finsearch_serviceconfig')->willReturn($cacheItemMock);

        $serviceConfigResource = new ServiceConfigResource(
            $cachePoolMock,
            $findologicClientFactory
        );

        $serviceConfigResource->isDirectIntegration($shopkey);
        $serviceConfigResource->isStaging($shopkey);
    }
}
