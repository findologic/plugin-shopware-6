<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Subscriber;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\CategoryHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;

class FrontendSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ConfigHelper;
    use SalesChannelHelper;
    use CategoryHelper;

    /**
     * @throws InvalidArgumentException
     */
    public function testHeaderPageletLoadedEvent(): void
    {
        $shopkey = $this->getShopkey();

        /** @var FindologicConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock($this);

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
                        $this->assertSame($shopkey, $snippet->getShopkey());

                        return true;
                    }
                )
            );

        $salesChannelContext = $this->buildSalesChannelContext();
        $headerPageletLoadedEventMock->expects($this->exactly(2))->method('getPagelet')
            ->willReturn($headerPageletMock);
        $headerPageletLoadedEventMock->expects($this->exactly(2))->method('getSalesChannelContext')
            ->willReturn($salesChannelContext);

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
