<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Subscriber;

use FINDOLOGIC\Api\Client;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\CategoryHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;

class BaseFrontendSubscriberTestCase extends TestCase
{
    use IntegrationTestBehaviour;
    use ConfigHelper;
    use SalesChannelHelper;
    use CategoryHelper;

    protected function setupProductListingTest(): array
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

    protected function setupProductSearchTest(): array
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

    protected function createDefaultSearchRequest(Request $request): SearchRequest
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

    protected function createDefaultRequest(): Request
    {
        $request = new Request();
        $request->headers->set('referer', 'http://localhost.shopware');
        $request->query->set('search', 'findologic');
        $request->headers->set('host', 'findologic.de');
        $request->server->set('REMOTE_ADDR', '192.168.0.1');
        $request->server->set('REQUEST_URI', 'http://localhost/findologic');
        $_SERVER['REMOTE_ADDR'] = '192.168.0.1';

        return $request;
    }

    protected function createDefaultNavigationRequest(Request $request): NavigationRequest
    {
        $navigationRequest = new NavigationRequest();
        $navigationRequest->setUserIp($request->getClientIp());
        $navigationRequest->setReferer($request->headers->get('referer'));
        $navigationRequest->setRevision('0.10.0');
        $navigationRequest->setOutputAdapter('XML_2.1');
        $navigationRequest->setShopUrl($request->getHost());
        $navigationRequest->setFirst(null);
        $navigationRequest->setCount(null);

        return $navigationRequest;
    }

    protected function getSearchExtensionsFromFrontendSubscriber(SimpleXMLElement $xml)
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
