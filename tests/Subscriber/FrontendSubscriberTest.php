<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Subscriber;

use FINDOLOGIC\FinSearch\Findologic\Api\ServiceConfig;
use FINDOLOGIC\FinSearch\Findologic\Client\FindologicClientFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;

class FrontendSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ConfigHelper;

    public function testHeaderPageletLoadedEvent(): void
    {
        $shopkey = $this->getShopkey();

        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configServiceMock->expects($this->at(0))->method('get')
            ->with('FinSearch.config.shopkey')
            ->willReturn($shopkey);
        $configServiceMock->expects($this->at(1))->method('get')
            ->with('FinSearch.config.active')
            ->willReturn(true);
        $configServiceMock->expects($this->at(2))->method('get')
            ->with('FinSearch.config.activeOnCategoryPages')
            ->willReturn(true);
        $configServiceMock->expects($this->at(3))->method('get')
            ->with('FinSearch.config.searchResultContainer')
            ->willReturn('fl-result');
        $configServiceMock->expects($this->at(4))->method('get')
            ->with('FinSearch.config.navigationResultContainer')
            ->willReturn('fl-navigation-result');
        $configServiceMock->expects($this->at(5))->method('get')
            ->with('FinSearch.config.integrationType')
            ->willReturn('Direct Integration');

        $headerPageletLoadedEventMock = $this->getMockBuilder(HeaderPageletLoadedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headerPageletMock = $this->getMockBuilder(HeaderPagelet::class)
            ->disableOriginalConstructor()
            ->getMock();

        $headerPageletMock->expects($this->at(0))
            ->method('addExtension')
            ->with(
                $this->callback(
                    function (string $name) {
                        $this->assertEquals('flConfig', $name);

                        return true;
                    }
                ),
                $this->callback(
                    function (Config $config) use ($shopkey) {
                        $this->assertSame($shopkey, $config->getShopkey());

                        return true;
                    }
                )
            );
        $headerPageletMock->expects($this->at(1))
            ->method('addExtension')
            ->with(
                $this->callback(
                    function (string $name) {
                        $this->assertEquals('flSnippet', $name);

                        return true;
                    }
                ),
                $this->callback(
                    function (Snippet $snippet) use ($shopkey) {
                        $this->assertSame(strtoupper(md5($shopkey)), $snippet->getHashedShopkey());

                        return true;
                    }
                )
            );

        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $customerGroupEntityMock = $this->getMockBuilder(CustomerGroupEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $customerGroupEntityMock->expects($this->once())->method('getId')->willReturn('1');
        $salesChannelContextMock->expects($this->once())
            ->method('getCurrentCustomerGroup')
            ->willReturn($customerGroupEntityMock);

        $headerPageletLoadedEventMock->expects($this->exactly(2))->method('getPagelet')->willReturn($headerPageletMock);
        $headerPageletLoadedEventMock->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn($salesChannelContextMock);

        $serviceConfig = new ServiceConfig();
        $serviceConfig->setFromArray(['directIntegration' => true, 'isStagingShop' => false]);
        $serviceConfigFromCache = serialize($serviceConfig);

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->once())->method('get')->willReturn($serviceConfigFromCache);
        $cachePoolMock->expects($this->once())->method('getItem')->willReturn($cacheItemMock);

        /** @var FindologicClientFactory|MockObject $findologicClientFactory */
        $findologicClientFactory = $this->getMockBuilder(FindologicClientFactory::class)->getMock();

        $serviceConfigResource = new ServiceConfigResource(
            $cachePoolMock,
            $findologicClientFactory
        );

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource
        );
        $frontendSubscriber->onHeaderLoaded($headerPageletLoadedEventMock);
    }
}
