<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Subscriber;

use FINDOLOGIC\Api\Client;
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
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock();

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
            $searchRequestFactory,
            null,
            null
        );
        $frontendSubscriber->onHeaderLoaded($headerPageletLoadedEventMock);
    }

    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function testOnSearch(): void
    {
        /** @var ProductSearchCriteriaEvent|MockObject $event */
        $event = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request();
        $request->headers->set('referer', 'http://localhost.shopware');
        $request->query->set('search', 'findologic');
        $request->headers->set('host', 'findologic.de');
        $request->server->set('REMOTE_ADDR', '192.168.0.1');

        $event->expects($this->once())->method('getRequest')->willReturn($request);

        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock();

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

        $response = new Xml21Response($this->getDemoXMLResponse());

        $productIds = array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $response->getProducts()
        );

        // Create example search request to match the expected request to be passed in the `send` method
        $searchRequest = new SearchRequest();
        $searchRequest->setUserIp($request->getClientIp());
        $searchRequest->setReferer($request->headers->get('referer'));
        $searchRequest->setRevision('0.10.0');
        $searchRequest->setOutputAdapter('XML_2.1');
        $searchRequest->setShopUrl($request->getHost());
        $searchRequest->setQuery('findologic');

        $apiClientMock->expects($this->once())->method('send')->with($searchRequest)->willReturn($response);

        $criteria = new Criteria($productIds);
        $event->expects($this->once())->method('getCriteria')->willReturn($criteria);

        $this->assertSame($productIds, $criteria->getIds());

        $frontendSubscriber = new FrontendSubscriber(
            $configServiceMock,
            $serviceConfigResource,
            $searchRequestFactory,
            $apiConfig,
            $apiClientMock
        );
        $frontendSubscriber->onSearch($event);
    }

    /**
     * Creates a system config service mock with default findologic config values initialized
     * Passing the data array will override any default values if needed
     */
    private function getDefaultFindologicConfigServiceMock(array $data = [])
    {
        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $active = $data['active'] ?? true;
        $shopkey = $data['shopkey'] ?? $this->getShopkey();
        $activeOnCategoryPages = $data['activeOnCategoryPages'] ?? true;
        $searchResultContainer = $data['searchResultContainer'] ?? 'fl-result';
        $navigationResultContainer = $data['navigationResultContainer'] ?? 'fl-navigation-result';
        $integrationType = $data['integrationType'] ?? 'Direct Integration';

        $configServiceMock->method('get')
            ->willReturnOnConsecutiveCalls(
                $active,
                $shopkey,
                $activeOnCategoryPages,
                $searchResultContainer,
                $navigationResultContainer,
                $integrationType,
                // Called second time with salesChannelID
                $active,
                $shopkey,
                $activeOnCategoryPages,
                $searchResultContainer,
                $navigationResultContainer,
                $integrationType
            );

        return $configServiceMock;
    }
}
