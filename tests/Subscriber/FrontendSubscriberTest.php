<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Subscriber;

use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
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

    /**
     * @throws InvalidArgumentException
     */
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

        /** @var HeaderPageletLoadedEvent|MockObject $headerPageletLoadedEventMock */
        $headerPageletLoadedEventMock = $this->getMockBuilder(HeaderPageletLoadedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var HeaderPagelet|MockObject $headerPageletMock */
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

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CustomerGroupEntity|MockObject $customerGroupEntityMock */
        $customerGroupEntityMock = $this->getMockBuilder(CustomerGroupEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $customerGroupEntityMock->expects($this->once())->method('getId')->willReturn('1');

        $salesChannelContextMock->expects($this->once())
            ->method('getCurrentCustomerGroup')
            ->willReturn($customerGroupEntityMock);

        $headerPageletLoadedEventMock->expects($this->exactly(2))->method('getPagelet')->willReturn($headerPageletMock);
        $headerPageletLoadedEventMock->expects($this->exactly(2))->method('getSalesChannelContext')
            ->willReturn($salesChannelContextMock);

        /** @var ServiceConfigResource|MockObject $serviceConfigResource */
        $serviceConfigResource = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource
        );
        $frontendSubscriber->onHeaderLoaded($headerPageletLoadedEventMock);
    }
}
