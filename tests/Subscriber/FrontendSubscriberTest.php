<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Subscriber;

use FINDOLOGIC\Api\Client;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use FINDOLOGIC\FinSearch\Tests\Traits\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\SalesChannel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class FrontendSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ConfigHelper;
    use SalesChannel;

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
        $navigationRequestFactory = new NavigationRequestFactory($cachePoolMock, $this->getContainer());

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $this->getContainer()->get(GenericPageLoader::class)
        );

        $frontendSubscriber->onHeaderLoaded($headerPageletLoadedEventMock);
    }

    public function responseProvider(): array
    {
        $response = new Xml21Response($this->getDemoXMLResponse());

        $productIds = array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $response->getProducts()
        );

        return [
            'Response matches the product Ids' => [
                'response' => $response,
                'productIds' => $productIds
            ],
        ];
    }

    /**
     * @dataProvider responseProvider
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function testProductSearchCriteria(?Xml21Response $response, array $productIds): void
    {
        [
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $apiConfig,
            $apiClientMock,
            $event,
            $searchRequest
        ] = $this->setupProductSearchTest();

        $apiClientMock->expects($this->once())
            ->method('send')
            ->with($searchRequest)
            ->willReturn($response);

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
            $navigationRequestFactory,
            $this->getContainer()->get(GenericPageLoader::class),
            $configMock,
            $apiConfig,
            $apiClientMock
        );
        $frontendSubscriber->onSearch($event);

        // Make sure that the product IDs are assigned correctly to the criteria after the onSearch event is triggered
        $this->assertSame($productIds, $event->getCriteria()->getIds());
    }

    /**
     * @dataProvider responseProvider
     *
     * @param int[] $productIds
     *
     * @throws InvalidArgumentException
     * @throws MissingRequestParameterException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function testProductListingCriteria(?Xml21Response $response, array $productIds): void
    {
        [
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $apiConfig,
            $apiClientMock,
            $event,
            $navigationRequest
        ] = $this->setupProductListingTest();

        $apiClientMock->expects($this->once())
            ->method('send')
            ->with($navigationRequest)
            ->willReturn($response);

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
            $navigationRequestFactory,
            $this->getContainer()->get(GenericPageLoader::class),
            $configMock,
            $apiConfig,
            $apiClientMock
        );
        $frontendSubscriber->onNavigation($event);

        // Make sure that the product IDs are assigned correctly
        $this->assertSame($productIds, $event->getCriteria()->getIds());
    }

    public function eventTypeProvider()
    {
        return [
            'ServiceNotAliveException is caught for search' => [true],
            'ServiceNotAliveException is caught for navigation' => [false],
        ];
    }

    /**
     * @dataProvider eventTypeProvider
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidArgumentException
     * @throws MissingRequestParameterException
     * @throws CategoryNotFoundException
     */
    public function testServiceNotAliveExceptionsAreCaught(bool $isSearch): void
    {
        if ($isSearch) {
            [
                $configServiceMock,
                $serviceConfigResource,
                $searchRequestFactory,
                $navigationRequestFactory,
                $apiConfig,
                $apiClientMock,
                $event,
                $searchRequest
            ] = $this->setupProductSearchTest();
        } else {
            [
                $configServiceMock,
                $serviceConfigResource,
                $searchRequestFactory,
                $navigationRequestFactory,
                $apiConfig,
                $apiClientMock,
                $event,
                $searchRequest
            ] = $this->setupProductListingTest();
        }

        $apiClientMock->expects($this->once())
            ->method('send')
            ->with($searchRequest)
            ->willThrowException(new ServiceNotAliveException('Service responded with an error'));

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
            $navigationRequestFactory,
            $this->getContainer()->get(GenericPageLoader::class),
            $configMock,
            $apiConfig,
            $apiClientMock
        );

        if ($isSearch) {
            $frontendSubscriber->onSearch($event);
        } else {
            $frontendSubscriber->onNavigation($event);
        }
        // Make sure that the product IDs empty due to exception
        $this->assertEmpty($event->getCriteria()->getIds());
    }

    private function setupProductListingTest(): array
    {
        $categoryPath = 'Kids & Music_Computers & Shoes';

        $request = new Request();
        $request->headers->set('referer', 'http://localhost.shopware');
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
        $navigationRequestFactory = new NavigationRequestFactory($cachePoolMock, $this->getContainer());

        $apiConfig = new ApiConfig();

        /** @var Client|MockObject $apiClientMock */
        $apiClientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$apiConfig])
            ->getMock();

        $context = $this->buildSalesChannelContext();

        $event = new ProductListingCriteriaEvent(
            $request,
            new Criteria(),
            $context
        );

        // Create example request to match the expected request to be passed in the `send` method
        $navigationRequest = new NavigationRequest();
        $navigationRequest->setUserIp($request->getClientIp());
        $navigationRequest->setReferer($request->headers->get('referer'));
        $navigationRequest->setRevision('0.10.0');
        $navigationRequest->setOutputAdapter('XML_2.1');
        $navigationRequest->setShopUrl($request->getHost());
        $navigationRequest->setSelected('cat', $categoryPath);

        return [
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $apiConfig,
            $apiClientMock,
            $event,
            $navigationRequest
        ];
    }

    private function setupProductSearchTest(): array
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
        $navigationRequestFactory = new NavigationRequestFactory($cachePoolMock, $this->getContainer());

        $apiConfig = new ApiConfig();

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

        return [
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $apiConfig,
            $apiClientMock,
            $event,
            $searchRequest
        ];
    }
}
