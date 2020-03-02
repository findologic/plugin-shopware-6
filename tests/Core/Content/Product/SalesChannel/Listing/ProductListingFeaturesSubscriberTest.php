<?php

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Query;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use SimpleXMLElement;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests in this test class act more like integration tests, as they mock the whole search stack.
 */
class ProductListingFeaturesSubscriberTest extends TestCase
{
    /** @var Connection|MockObject */
    private $connectionMock;

    /** @var EntityRepository|MockObject */
    private $entityRepositoryMock;

    /** @var ProductListingSortingRegistry|MockObject */
    private $productListingSortingRegistry;

    /** @var NavigationRequestFactory|MockObject */
    private $navigationRequestFactoryMock;

    /** @var SearchRequestFactory|MockObject */
    private $searchRequestFactoryMock;

    /** @var SystemConfigService|MockObject */
    private $systemConfigServiceMock;

    /** @var ServiceConfigResource|MockObject */
    private $serviceConfigResourceMock;

    /** @var GenericPageLoader|MockObject */
    private $genericPageLoaderMock;

    /** @var Container|MockObject */
    private $containerMock;

    /** @var Config|MockObject */
    private $configMock;

    /** @var ApiConfig|MockObject */
    private $apiConfigMock;

    /** @var ApiClient|MockObject */
    private $apiClientMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->initMocks();
    }

    private function initMocks(): void
    {
        $this->connectionMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->entityRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productListingSortingRegistry = $this->getMockBuilder(ProductListingSortingRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->navigationRequestFactoryMock = $this->getMockBuilder(NavigationRequestFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->searchRequestFactoryMock = $this->getMockBuilder(SearchRequestFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->systemConfigServiceMock = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->serviceConfigResourceMock = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->genericPageLoaderMock = $this->getMockBuilder(GenericPageLoader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->containerMock = $this->getMockBuilder(Container::class)->disableOriginalConstructor()->getMock();
        $this->configMock = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $this->apiConfigMock = $this->getMockBuilder(ApiConfig::class)->disableOriginalConstructor()->getMock();
        $this->apiClientMock = $this->getMockBuilder(ApiClient::class)->disableOriginalConstructor()->getMock();
    }

    private function getDefaultProductListingFeaturesSubscriber(): ProductListingFeaturesSubscriber
    {
        return new ProductListingFeaturesSubscriber(
            $this->connectionMock,
            $this->entityRepositoryMock,
            $this->productListingSortingRegistry,
            $this->navigationRequestFactoryMock,
            $this->searchRequestFactoryMock,
            $this->systemConfigServiceMock,
            $this->serviceConfigResourceMock,
            $this->genericPageLoaderMock,
            $this->containerMock,
            $this->configMock,
            $this->apiConfigMock,
            $this->apiClientMock
        );
    }

    private function getRawResponse(): SimpleXMLElement
    {
        return new SimpleXMLElement(file_get_contents(__DIR__ . '/../../../../../MockData/XMLResponse/demo.xml'));
    }

    private function getDefaultResponse(): Xml21Response
    {
        return new Xml21Response(file_get_contents(__DIR__ . '/../../../../../MockData/XMLResponse/demo.xml'));
    }

    private function getDefaultRequestMock(): Request
    {
        /** @var Request|MockObject $requestMock */
        $requestMock = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();

        $queryMock = $this->getMockBuilder(ParameterBag::class)->getMock();
        $queryMock->expects($this->at(0))
            ->method('getInt')
            ->willReturn(24);
        $queryMock->expects($this->at(1))
            ->method('getInt')
            ->willReturn(1);
        $queryMock->expects($this->any())->method('get')->willReturn('');

        $requestMock->query = $queryMock;

        return $requestMock;
    }

    public function requestProvider(): array
    {
        return [
            'search request' => [
                'endpoint' => 'handleSearchRequest',
                'expectedProducts' => [
                    '019111105-37900' => '019111105-37900',
                    '029214085-37860' => '029214085-37860'
                ],
                'isNavigationRequest' => false
            ],
            'navigation request' => [
                'endpoint' => 'handleListingRequest',
                'expectedProducts' => [
                    '019111105-37900' => '019111105-37900',
                    '029214085-37860' => '029214085-37860'
                ],
                'isNavigationRequest' => true
            ]
        ];
    }

//    /**
//     * @dataProvider requestProvider
//     * @param string $endpoint
//     * @param array $expectedProducts
//     * @param bool $isNavigationRequest
//     */
//    public function testResponseMatchesProductIds(string $endpoint, array $expectedProducts, bool $isNavigationRequest)
//    {
//        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
//        $this->apiClientMock->expects($this->any())->method('send')->willReturn($this->getDefaultResponse());
//
//        /** @var ProductSearchCriteriaEvent|MockObject $eventMock */
//        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $eventMock->expects($this->any())->method('getRequest')->willReturn($this->getDefaultRequestMock());
//
//        $findologicEnabledMock = $this->getMockBuilder(FindologicEnabled::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//        $findologicEnabledMock->expects($this->any())->method('getEnabled')->willReturn(true);
//
//        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
//        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicEnabledMock);
//        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);
//
//        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
//        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);
//        $criteriaMock->expects($this->any())->method('assign')->with([
//            'source' => null,
//            'sorting' => [],
//            'filters' => [],
//            'postFilters' => [],
//            'aggregations' => [],
//            'queries' => [],
//            'groupFields' => [],
//            'offset' => null,
//            'limit' => null,
//            'totalCountMode' => 0,
//            'associations' => [],
//            'ids' => $expectedProducts,
//            'states' => [],
//            'inherited' => false,
//            'term' => null,
//            'extensions' => [
//                'flPagination' => new Pagination(24, 0, 1808)
//            ]
//        ]);
//
//        if ($isNavigationRequest) {
//            $headerMock = $this->getMockBuilder(HeaderPagelet::class)
//                ->disableOriginalConstructor()
//                ->getMock();
//
//            // TODO: Make this injectable via constructor arguments if possible.
//            $pageMock = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->getMock();
//            $pageMock->expects($this->any())->method('getHeader')->willReturn($headerMock);
//            $reflection = new ReflectionClass($pageMock);
//            $reflectionProperty = $reflection->getProperty('header');
//            $reflectionProperty->setAccessible(true);
//            $reflectionProperty->setValue($pageMock, $headerMock);
//            $this->genericPageLoaderMock->expects($this->any())->method('load')->willReturn($pageMock);
//
//            $categoryTreeMock = $this->getMockBuilder(Tree::class)->disableOriginalConstructor()->getMock();
//            $headerMock->expects($this->once())->method('getNavigation')->willReturn($categoryTreeMock);
//
//            $categoryEntityMock = $this->getMockBuilder(CategoryEntity::class)
//                ->disableOriginalConstructor()
//                ->getMock();
//
//            $categoryTreeMock->expects($this->once())->method('getActive')->willReturn($categoryEntityMock);
//
//            $categoryEntityMock->expects($this->once())->method('getBreadcrumb')
//                ->willReturn(['Deutsch', 'Freizeit & Elektro']);
//        }
//
//        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
//        $subscriber->{$endpoint}($eventMock);
//    }
//
//    public function sortingProvider(): array
//    {
//        return [
//            'ProductNameSorting is ASC' => [
//                'fieldSorting' => new FieldSorting('product.name', 'ASC'),
//                'expectedOrder' => 'label ASC'
//            ],
//            'ProductNameSorting is DESC' => [
//                'fieldSorting' => new FieldSorting('product.name', 'DESC'),
//                'expectedOrder' => 'label DESC'
//            ],
//            'PriceSorting is ASC' => [
//                'fieldSorting' => new FieldSorting('product.listingPrices', 'ASC'),
//                'expectedOrder' => 'price ASC'
//            ],
//            'PriceSorting is DESC' => [
//                'fieldSorting' => new FieldSorting('product.listingPrices', 'DESC'),
//                'expectedOrder' => 'price DESC'
//            ],
//            'ScoreSorting is ASC' => [
//                'fieldSorting' => new FieldSorting('_score', 'ASC'),
//                'expectedOrder' => '' // Order generated by customer login.
//            ],
//            'ScoreSorting is DESC' => [
//                'fieldSorting' => new FieldSorting('_score', 'DESC'),
//                'expectedOrder' => '' // Order generated by customer login.
//            ],
//            'ReleaseDateSorting is ASC' => [
//                'fieldSorting' => new FieldSorting('product.dateadded', 'ASC'),
//                'expectedOrder' => '' // Currently not supported by Shopware.
//            ],
//            'ReleaseDateSorting is DESC' => [
//                'fieldSorting' => new FieldSorting('product.dateadded', 'DESC'),
//                'expectedOrder' => '' // Currently not supported by Shopware.
//            ],
//        ];
//    }
//
//    /**
//     * @dataProvider sortingProvider
//     * @param FieldSorting $fieldSorting
//     * @param string $expectedOrder
//     */
//    public function testSortingIsSubmitted(FieldSorting $fieldSorting, string $expectedOrder)
//    {
//        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
//        $this->apiClientMock->expects($this->any())->method('send')->willReturn($this->getDefaultResponse());
//
//        /** @var ProductSearchCriteriaEvent|MockObject $eventMock */
//        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $eventMock->expects($this->any())->method('getRequest')->willReturn($this->getDefaultRequestMock());
//
//        $findologicEnabledMock = $this->getMockBuilder(FindologicEnabled::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//        $findologicEnabledMock->expects($this->any())->method('getEnabled')->willReturn(true);
//
//        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
//        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicEnabledMock);
//        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);
//
//        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
//        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);
//
//        $criteriaMock->expects($this->any())->method('getSorting')->willReturn([$fieldSorting]);
//
//        $searchRequest = new SearchRequest();
//        $this->searchRequestFactoryMock->expects($this->any())
//            ->method('getInstance')
//            ->willReturn($searchRequest);
//
//        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
//        $subscriber->handleSearchRequest($eventMock);
//
//        if ($expectedOrder !== '') {
//            $this->assertEquals($expectedOrder, $searchRequest->getParams()['order']);
//        } else {
//            $this->assertArrayNotHasKey('order', $searchRequest->getParams());
//        }
//    }
//
//    /**
//     * @dataProvider requestProvider
//     * @param string $endpoint
//     * @param array $expectedProducts
//     * @param bool $isNavigationRequest
//     */
//    public function testServiceNotAliveExceptionsAreCaught(
//        string $endpoint,
//        array $expectedProducts,
//        bool $isNavigationRequest
//    ) {
//        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
//        $this->apiClientMock->expects($this->any())->method('send')->willThrowException(
//            new ServiceNotAliveException('dead: This service is currently unreachable.')
//        );
//
//        /** @var ProductSearchCriteriaEvent|MockObject $eventMock */
//        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $eventMock->expects($this->any())->method('getRequest')->willReturn($this->getDefaultRequestMock());
//
//        $findologicEnabledMock = $this->getMockBuilder(FindologicEnabled::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//        $findologicEnabledMock->expects($this->any())->method('getEnabled')->willReturn(true);
//
//        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
//        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicEnabledMock);
//        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);
//
//        /** @var Criteria|MockObject $criteriaMock */
//        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
//        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);
//        $criteriaMock->expects($this->any())->method('assign')->with([]); // Should be empty.
//
//        if ($isNavigationRequest) {
//            $headerMock = $this->getMockBuilder(HeaderPagelet::class)
//                ->disableOriginalConstructor()
//                ->getMock();
//
//            // TODO: Make this injectable via constructor arguments if possible.
//            $pageMock = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->getMock();
//            $pageMock->expects($this->any())->method('getHeader')->willReturn($headerMock);
//            $reflection = new ReflectionClass($pageMock);
//            $reflectionProperty = $reflection->getProperty('header');
//            $reflectionProperty->setAccessible(true);
//            $reflectionProperty->setValue($pageMock, $headerMock);
//            $this->genericPageLoaderMock->expects($this->any())->method('load')->willReturn($pageMock);
//
//            $categoryTreeMock = $this->getMockBuilder(Tree::class)->disableOriginalConstructor()->getMock();
//            $headerMock->expects($this->once())->method('getNavigation')->willReturn($categoryTreeMock);
//
//            $categoryEntityMock = $this->getMockBuilder(CategoryEntity::class)
//                ->disableOriginalConstructor()
//                ->getMock();
//
//            $categoryTreeMock->expects($this->once())->method('getActive')->willReturn($categoryEntityMock);
//
//            $categoryEntityMock->expects($this->once())->method('getBreadcrumb')
//                ->willReturn(['Deutsch', 'Freizeit & Elektro']);
//        }
//
//        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
//        $subscriber->{$endpoint}($eventMock);
//    }
//
//    public function testResponseHasPromotion()
//    {
//        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
//        $this->apiClientMock->expects($this->any())->method('send')->willReturn($this->getDefaultResponse());
//
//        /** @var ProductSearchCriteriaEvent|MockObject $eventMock */
//        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $eventMock->expects($this->any())->method('getRequest')->willReturn($this->getDefaultRequestMock());
//
//        $findologicEnabledMock = $this->getMockBuilder(FindologicEnabled::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//        $findologicEnabledMock->expects($this->any())->method('getEnabled')->willReturn(true);
//
//        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
//        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicEnabledMock);
//        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
//            ['flEnabled'],
//            ['flSmartDidYouMean'],
//            ['flPromotion', new Promotion('https://promotion.com/promotion.png', 'https://promotion.com/')]
//        );
//        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);
//
//        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
//        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);
//
//        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
//        $subscriber->handleSearchRequest($eventMock);
//    }
//
//    public function testResponseHasNoPromotion()
//    {
//        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
//        $response = $this->getRawResponse();
//        unset($response->promotion);
//
//        $this->apiClientMock->expects($this->any())->method('send')->willReturn(
//            new Xml21Response($response->asXML())
//        );
//
//        /** @var ProductSearchCriteriaEvent|MockObject $eventMock */
//        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $eventMock->expects($this->any())->method('getRequest')->willReturn($this->getDefaultRequestMock());
//
//        $findologicEnabledMock = $this->getMockBuilder(FindologicEnabled::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//        $findologicEnabledMock->expects($this->any())->method('getEnabled')->willReturn(true);
//
//        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
//        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicEnabledMock);
//        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
//            ['flEnabled'],
//            ['flSmartDidYouMean']
//        );
//        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);
//
//        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
//        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);
//
//        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
//        $subscriber->handleSearchRequest($eventMock);
//    }

    public function testContainsDidYouMeanQuery()
    {
        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse();
        unset($response->promotion);

        $this->apiClientMock->expects($this->any())->method('send')->willReturn(
            new Xml21Response($response->asXML())
        );

        /** @var ProductSearchCriteriaEvent|MockObject $eventMock */
        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock->expects($this->any())->method('getRequest')->willReturn($this->getDefaultRequestMock());

        $findologicEnabledMock = $this->getMockBuilder(FindologicEnabled::class)
            ->disableOriginalConstructor()
            ->getMock();
        $findologicEnabledMock->expects($this->any())->method('getEnabled')->willReturn(true);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicEnabledMock);
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['flEnabled'],
            [
                'flSmartDidYouMean',
                new SmartDidYouMean(
                    new Query(new SimpleXMLElement('<query>
        <limit first="0" count="24" />
        <queryString type="corrected">ps3</queryString>
        <originalQuery allow-override="1">original query</originalQuery>
        <didYouMeanQuery>ps4</didYouMeanQuery>
    </query>')),
                    null
                )
            ]
        );
        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);

        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleSearchRequest($eventMock);
    }

}
