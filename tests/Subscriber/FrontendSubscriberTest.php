<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Subscriber;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\PageInformation;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\CategoryHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class FrontendSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ConfigHelper;
    use SalesChannelHelper;
    use CategoryHelper;

    public function headerPageletLoadedEventProvider(): array
    {
        return [
            'Search Request' => [
                'requestParams' => [
                    ['search' => 't-shirt'],
                    [],
                    [],
                    [],
                    [],
                    ['REQUEST_URI' => 'https://example.com/search']
                ],
                'expectedPageInformation' => [
                    'isSearchPage' => true,
                    'isNavigationPage' => false
                ]
            ],
            'Category Request' => [
                'requestParams' => [
                    [],
                    [],
                    ['navigationId' => 5],
                    [],
                    [],
                    ['REQUEST_URI' => 'https://example.com/categoryFive']
                ],
                'expectedPageInformation' => [
                    'isSearchPage' => false,
                    'isNavigationPage' => true
                ]
            ]
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @dataProvider headerPageletLoadedEventProvider
     */
    public function testHeaderPageletLoadedEvent(array $requestParams, array $expectedPageInformation): void
    {
        $shopkey = $this->getShopkey();

        /** @var FindologicConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock();

        /** @var HeaderPageletLoadedEvent|MockObject $headerPageletLoadedEventMock */
        $headerPageletLoadedEventMock = $this->getMockBuilder(HeaderPageletLoadedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var HeaderPagelet|MockObject $headerPageletMock */
        $headerPageletMock = $this->getMockBuilder(HeaderPagelet::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request(
            $requestParams[0],
            $requestParams[1],
            $requestParams[2],
            $requestParams[3],
            $requestParams[4],
            $requestParams[5]
        );

        $headerPageletMock->expects($this->any())
            ->method('addExtension')
            ->withConsecutive([
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
            ], [
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
            ], [
                $this->callback(
                    function (string $name) {
                        $this->assertEquals('flPageInformation', $name);

                        return true;
                    }
                ),
                $this->callback(
                    function (PageInformation $pageInformation) use ($expectedPageInformation) {
                        $this->assertSame(
                            $expectedPageInformation['isSearchPage'],
                            $pageInformation->getIsSearchPage()
                        );
                        $this->assertSame(
                            $expectedPageInformation['isNavigationPage'],
                            $pageInformation->getIsNavigationPage()
                        );

                        return true;
                    }
                ),
            ]);

        $salesChannelContext = $this->buildSalesChannelContext();
        $headerPageletLoadedEventMock->expects($this->exactly(3))->method('getPagelet')
            ->willReturn($headerPageletMock);
        $headerPageletLoadedEventMock->expects($this->exactly(2))->method('getSalesChannelContext')
            ->willReturn($salesChannelContext);
        $headerPageletLoadedEventMock->expects($this->once())->method('getRequest')
            ->willReturn($request);

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
