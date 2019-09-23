<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearchTests\Subscriber;

use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;

class FrontendSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testHeaderPageletLoadedEvent()
    {
        $configServiceMock = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configServiceMock->expects($this->once())->method('get')
            ->with(['FinSearch.config.shopkey'])
            ->willReturn('000000000000000ZZZZZZZZZZZZZZZZZ');
        $configServiceMock->expects($this->once())->method('get')
            ->with(['FinSearch.config.active'])->willReturn(true);
        $configServiceMock->expects($this->once())->method('get')
            ->with(['FinSearch.config.activeOnCategoryPages'])
            ->willReturn(true);
        $configServiceMock->expects($this->once())->method('get')
            ->with(['FinSearch.config.searchResultContainer'])
            ->willReturn('fl-result');
        $configServiceMock->expects($this->once())->method('get')
            ->with(['FinSearch.config.navigationResultContainer'])
            ->willReturn('fl-navigation-result');
        $configServiceMock->expects($this->once())->method('get')
            ->with(['FinSearch.config.integrationType'])
            ->willReturn('Direct Integration');

        $headerLoadedEventMock = $this->getMockBuilder(HeaderPageletLoadedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headerPageletMock = $this->getMockBuilder(HeaderPagelet::class)
            ->disableOriginalConstructor()
            ->getMock();

        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $customerGroupMock = $this->getMockBuilder(CustomerGroupEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $customerGroupMock->expects($this->once())->method('getId')->willReturn('1');
        $salesChannelContextMock->expects($this->once())
            ->method('getCurrentCustomerGroup')
            ->willReturn($customerGroupMock);

        $headerLoadedEventMock->expects($this->once())->method('getPagelet')->willReturn($headerPageletMock);
        $headerLoadedEventMock->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn($salesChannelContextMock);

        $frontendSubscriber = new FrontendSubscriber($configServiceMock);
        $frontendSubscriber->onHeaderLoaded($headerLoadedEventMock);
    }
}
