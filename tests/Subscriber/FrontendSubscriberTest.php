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
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\CategoryHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
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
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;

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
            $this->getContainer()->get(GenericPageLoader::class),
            $this->getContainer()
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
        $configMock->expects($this->exactly(2))->method('getShopkey')->willReturn($this->getShopkey());

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $this->getContainer()->get(GenericPageLoader::class),
            $this->getContainer(),
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
        $configMock->expects($this->exactly(2))->method('getShopkey')->willReturn($this->getShopkey());

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $this->getContainer()->get(GenericPageLoader::class),
            $this->getContainer(),
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
        $configMock->expects($this->exactly(2))->method('getShopkey')->willReturn($this->getShopkey());

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $this->getContainer()->get(GenericPageLoader::class),
            $this->getContainer(),
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
        $categoryPath = 'First Level Category_Second Level Category';

        $parent = Uuid::randomHex();
        $recordA = Uuid::randomHex();
        $recordB = Uuid::randomHex();
        $recordC = Uuid::randomHex();

        $categories = [
            ['id' => $parent, 'name' => 'First Level Category', 'parentId' => $this->fetchIdFromDatabase('category')],
            ['id' => $recordA, 'name' => 'Second Level Category', 'parentId' => $parent],
            ['id' => $recordC, 'name' => 'Third Level Category', 'parentId' => $recordA],
            [
                'id' => $recordB,
                'name' => 'Second Level Category 2',
                'parentId' => $parent,
                'afterCategoryId' => $recordA
            ],
        ];

        $this->createTestCategory($categories);

        $request = $this->createDefaultRequest();
        $request->query->set('navigationId', $recordA);

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

        $navigationRequest = $this->createDefaultNavigationRequest($request);
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
        $request = $this->createDefaultRequest();

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

        $searchRequest = $this->createDefaultSearchRequest($request);

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

    public function testResponseHasPromotion()
    {
        $expectedExtension = new Promotion('https://promotion.com/promotion.png', 'https://promotion.com/');

        $xml = $this->getDemoXML();
        $extensions = $this->getSearchExtensionsFromFrontendSubscriber($xml);

        $this->assertArrayHasKey('flPromotion', $extensions);
        $this->assertEquals($expectedExtension, $extensions['flPromotion']);
    }

    public function testResponseDoesNotHavePromotion()
    {
        $xml = $this->getDemoXML();
        unset($xml->promotion);

        $extensions = $this->getSearchExtensionsFromFrontendSubscriber($xml);
        $this->assertArrayNotHasKey('flPromotion', $extensions);
    }

    public function testResponseHasLandingPage()
    {
        $this->markTestSkipped('Issue due to redirection');
        $xml = $this->getDemoXML();
        $xml->addChild('landingPage')->addAttribute('link', 'https://www.landingpage.io/agb/');

        $extensions = $this->getSearchExtensionsFromFrontendSubscriber($xml);
        $this->assertEmpty($extensions);
    }

    public function testResponseContainsDidYouMeanQuery()
    {
        $xml = $this->getDemoXML();

        $extensions = $this->getSearchExtensionsFromFrontendSubscriber($xml);
        $this->assertNotEmpty($extensions);
        $this->assertArrayHasKey('flSmartDidYouMean', $extensions);

        $smartDidYouMeanExtension = $extensions['flSmartDidYouMean'];
        $smartDidYouMeanParameters = $smartDidYouMeanExtension->getVars();
        $this->assertSame('did-you-mean', $smartDidYouMeanParameters['type']);
        $this->assertSame('/findologic?search=ps4&forceOriginalQuery=1', $smartDidYouMeanParameters['link']);
        $this->assertSame('ps4', $smartDidYouMeanParameters['alternativeQuery']);
    }

    public function testResponseContainsImprovedQuery()
    {
        $xml = $this->getDemoXML();
        unset($xml->query->didYouMeanQuery);
        unset($xml->query->queryString);

        $xml->query->addChild('queryString', 'ps3')->addAttribute('type', 'improved');

        $extensions = $this->getSearchExtensionsFromFrontendSubscriber($xml);
        $this->assertNotEmpty($extensions);
        $this->assertArrayHasKey('flSmartDidYouMean', $extensions);

        $smartDidYouMeanExtension = $extensions['flSmartDidYouMean'];
        $smartDidYouMeanParameters = $smartDidYouMeanExtension->getVars();
        $this->assertSame('improved', $smartDidYouMeanParameters['type']);
        $this->assertSame('/findologic?search=original+query&forceOriginalQuery=1', $smartDidYouMeanParameters['link']);
        $this->assertSame('ps3', $smartDidYouMeanParameters['alternativeQuery']);
    }

    public function testResponseContainsCorrectedQuery()
    {
        $xml = $this->getDemoXML();
        unset($xml->query->didYouMeanQuery);

        $extensions = $this->getSearchExtensionsFromFrontendSubscriber($xml);
        $this->assertNotEmpty($extensions);
        $this->assertArrayHasKey('flSmartDidYouMean', $extensions);

        $smartDidYouMeanExtension = $extensions['flSmartDidYouMean'];
        $smartDidYouMeanParameters = $smartDidYouMeanExtension->getVars();
        $this->assertSame('corrected', $smartDidYouMeanParameters['type']);
        $this->assertNull($smartDidYouMeanParameters['link']);
        $this->assertSame('ps3', $smartDidYouMeanParameters['alternativeQuery']);
    }

    private function createDefaultSearchRequest(Request $request): SearchRequest
    {
        // Create example search request to match the expected request to be passed in the `send` method
        $searchRequest = new SearchRequest();
        $searchRequest->setUserIp($request->getClientIp());
        $searchRequest->setReferer($request->headers->get('referer'));
        $searchRequest->setRevision('0.10.0');
        $searchRequest->setOutputAdapter('XML_2.1');
        $searchRequest->setShopUrl($request->getHost());
        $searchRequest->setQuery('findologic');
        $searchRequest->setFirst(null);
        $searchRequest->setCount(null);

        if ($request->get('forceOriginalQuery', false)) {
            $searchRequest->setForceOriginalQuery();
        }

        return $searchRequest;
    }

    private function createDefaultRequest(): Request
    {
        $request = new Request();
        $request->headers->set('referer', 'http://localhost.shopware');
        $request->query->set('search', 'findologic');
        $request->headers->set('host', 'findologic.de');
        $request->server->set('REMOTE_ADDR', '192.168.0.1');
        $request->server->set('REQUEST_URI', 'http://localhost/findologic');

        return $request;
    }

    private function createDefaultNavigationRequest(Request $request): NavigationRequest
    {
        $navigationRequest = new NavigationRequest();
        $navigationRequest->setUserIp($request->getClientIp());
        $navigationRequest->setReferer($request->headers->get('referer'));
        $navigationRequest->setRevision('0.10.0');
        $navigationRequest->setOutputAdapter('XML_2.1');
        $navigationRequest->setShopUrl($request->getHost());

        return $navigationRequest;
    }

    private function getSearchExtensionsFromFrontendSubscriber(SimpleXMLElement $xml)
    {
        $response = new Xml21Response($xml->asXML());

        $request = $this->createDefaultRequest();

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

        $searchRequest = $this->createDefaultSearchRequest($request);

        $apiClientMock->expects($this->once())
            ->method('send')
            ->with($searchRequest)
            ->willReturn($response);

        /** @var Config|MockObject $configMock */
        $configMock = $this->getMockBuilder(Config::class)
            ->setConstructorArgs([$configServiceMock, $serviceConfigResource])
            ->getMock();
        $configMock->expects($this->once())->method('isActive')->willReturn(true);
        $configMock->expects($this->exactly(2))->method('getShopkey')->willReturn($this->getShopkey());

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $navigationRequestFactory,
            $this->getContainer()->get(GenericPageLoader::class),
            $this->getContainer(),
            $configMock,
            $apiConfig,
            $apiClientMock
        );

        $frontendSubscriber->onSearch($event);
        $extensions = $event->getContext()->getExtensions();

        return $extensions;
    }
}
