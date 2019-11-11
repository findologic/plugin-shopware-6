<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Subscriber;

use FINDOLOGIC\Api\Client;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use FINDOLOGIC\FinSearch\Tests\ConfigHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

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

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $searchRequestFactory = new SearchRequestFactory($cachePoolMock, $this->getContainer());

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory
        );

        $frontendSubscriber->onHeaderLoaded($headerPageletLoadedEventMock);
    }

    public function apiClientExceptionProvider(): array
    {
        $response = new Xml21Response($this->getDemoXMLResponse());

        $productIds = array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $response->getProducts()
        );

        return [
            'ServiceNotAliveException is thrown' => [null, [], 'Service responded with an error'],
            'ServiceNotAliveException is not thrown' => [$response, $productIds, ''],
        ];
    }

    /**
     * @dataProvider apiClientExceptionProvider
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function testProductSearchCriteria(?Xml21Response $response, array $productIds, string $message): void
    {
        $request = new Request();
        $request->headers->set('referer', 'http://localhost.shopware');
        $request->query->set('search', 'findologic');
        $request->headers->set('host', 'findologic.de');
        $request->server->set('REMOTE_ADDR', '192.168.0.1');

        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock($this);

        /** @var ServiceConfigResource|MockObject $serviceConfigResource */
        $serviceConfigResource = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cacheItemMock->expects($this->exactly(2))->method('get')->willReturn('0.10.0');

        $cachePoolMock->expects($this->once())
            ->method('getItem')
            ->with('finsearch_version')
            ->willReturn($cacheItemMock);

        $searchRequestFactory = new SearchRequestFactory($cachePoolMock, $this->getContainer());

        $apiConfig = new \FINDOLOGIC\Api\Config();

        /** @var Client|MockObject $apiClientMock */
        $apiClientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$apiConfig])
            ->getMock();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $event = new ProductSearchCriteriaEvent(
            $request,
            new Criteria(),
            $context
        );

        // Create example search request to match the expected request to be passed in the `send` method
        $searchRequest = new SearchRequest();
        $searchRequest->setUserIp($request->getClientIp());
        $searchRequest->setReferer($request->headers->get('referer'));
        $searchRequest->setRevision('0.10.0');
        $searchRequest->setOutputAdapter('XML_2.1');
        $searchRequest->setShopUrl($request->getHost());
        $searchRequest->setQuery('findologic');

        try {
            if ($response === null) {
                $apiClientMock->expects($this->once())
                    ->method('send')
                    ->with($searchRequest)
                    ->willThrowException(new ServiceNotAliveException($message));
            } else {
                $apiClientMock->expects($this->once())
                    ->method('send')
                    ->with($searchRequest)
                    ->willReturn($response);
            }
        } catch (ServiceNotAliveException $e) {
            $this->expectExceptionMessage(sprintf('The service is not alive. Reason: %s', $message));
        }

        /** @var Config|MockObject $configMock */
        $configMock = $this->getMockBuilder(Config::class)
            ->setConstructorArgs([$configServiceMock, $serviceConfigResource])
            ->getMock();
        $configMock->expects($this->once())->method('isActive')->willReturn(true);
        $configMock->expects($this->once())->method('getShopkey')->willReturn($this->getShopkey());

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $configMock,
            $apiConfig,
            $apiClientMock
        );
        $frontendSubscriber->onSearch($event);

        // Make sure that the product IDs are assigned correctly to the criteria after the onSearch event is triggered
        $this->assertSame($productIds, $event->getCriteria()->getIds());
    }
}
