<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
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

    private function getRawResponse(string $file = 'demo.xml'): SimpleXMLElement
    {
        return new SimpleXMLElement(
            file_get_contents(
                __DIR__ . sprintf('/../../../../../MockData/XMLResponse/%s', $file)
            )
        );
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

    private function buildSmartDidYouMeanQueryElement(
        ?string $didYouMeanQuery = null,
        ?string $improvedQuery = null,
        ?string $correctedQuery = null
    ): SimpleXMLElement {
        $rawXML = <<<XML
<query>
    <limit first="0" count="24" />
    <queryString>ps3</queryString>
</query>
XML;
        $element = new SimpleXMLElement($rawXML);

        if ($didYouMeanQuery) {
            $element->addChild('didYouMeanQuery', $didYouMeanQuery);
        }
        if ($improvedQuery) {
            $element->queryString->addAttribute('type', 'improved');
            $element->addChild('originalQuery', $improvedQuery);
        }
        if ($correctedQuery) {
            $element->queryString->addAttribute('type', 'corrected');
            $element->addChild('originalQuery', $correctedQuery);
        }

        return $element;
    }

    /**
     * @param Xml21Response|null $response
     * @param Request|null $request
     *
     * @return MockObject|ProductSearchCriteriaEvent
     */
    private function setUpSearchRequestMocks(
        Xml21Response $response = null,
        Request $request = null
    ): ProductSearchCriteriaEvent {
        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
        if ($response === null) {
            $response = $this->getDefaultResponse();
        }

        $this->apiClientMock->expects($this->any())
            ->method('send')
            ->willReturn($response);

        /** @var ProductSearchCriteriaEvent|MockObject $eventMock */
        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($request === null) {
            $request = $this->getDefaultRequestMock();
        }
        $eventMock->expects($this->any())->method('getRequest')->willReturn($request);

        $findologicEnabled = new FindologicEnabled();
        $smartDidYouMean = new SmartDidYouMean($response->getQuery(), null);
        $defaultExtensionMap = [
            ['flEnabled', $findologicEnabled],
            ['flSmartDidYouMean', $smartDidYouMean]
        ];

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['flEnabled', $findologicEnabled],
            ['flSmartDidYouMean', $smartDidYouMean]
        );
        $contextMock->expects($this->any())->method('getExtension')->willReturnMap($defaultExtensionMap);
        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);

        return $eventMock;
    }

    private function setUpNavigationRequestMocks(): void
    {
        $headerMock = $this->getMockBuilder(HeaderPagelet::class)
            ->disableOriginalConstructor()
            ->getMock();

        // TODO: Make this injectable via constructor arguments if possible.
        $pageMock = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->getMock();
        $pageMock->expects($this->any())->method('getHeader')->willReturn($headerMock);
        $reflection = new ReflectionClass($pageMock);
        $reflectionProperty = $reflection->getProperty('header');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($pageMock, $headerMock);
        $this->genericPageLoaderMock->expects($this->any())->method('load')->willReturn($pageMock);

        $categoryTreeMock = $this->getMockBuilder(Tree::class)->disableOriginalConstructor()->getMock();
        $headerMock->expects($this->once())->method('getNavigation')->willReturn($categoryTreeMock);

        $categoryEntityMock = $this->getMockBuilder(CategoryEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $categoryTreeMock->expects($this->once())->method('getActive')->willReturn($categoryEntityMock);

        $categoryEntityMock->expects($this->once())->method('getBreadcrumb')
            ->willReturn(['Deutsch', 'Freizeit & Elektro']);
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
                'expectedProducts' => [],
                'isNavigationRequest' => true
            ]
        ];
    }

    /**
     * @dataProvider requestProvider
     *
     * @param string $endpoint
     * @param array $expectedProducts
     * @param bool $isNavigationRequest
     */
    public function testResponseMatchesProductIds(
        string $endpoint,
        array $expectedProducts,
        bool $isNavigationRequest
    ): void {
        $eventMock = $this->setUpSearchRequestMocks($this->getDefaultResponse());

        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $criteriaMock->expects($this->any())->method('assign')->with(
            [
                'source' => null,
                'sorting' => [],
                'filters' => [],
                'postFilters' => [],
                'aggregations' => [],
                'queries' => [],
                'groupFields' => [],
                'offset' => null,
                'limit' => null,
                'totalCountMode' => 0,
                'associations' => [],
                'ids' => $expectedProducts,
                'states' => [],
                'inherited' => false,
                'term' => null,
                'extensions' => [
                    'flPagination' => new Pagination(24, 0, 1808)
                ]
            ]
        );

        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

        if ($isNavigationRequest) {
            $this->setUpNavigationRequestMocks();
        }

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->{$endpoint}($eventMock);
    }

    public function sortingProvider(): array
    {
        return [
            'ProductNameSorting is ASC' => [
                'fieldSorting' => new FieldSorting('product.name', 'ASC'),
                'expectedOrder' => 'label ASC'
            ],
            'ProductNameSorting is DESC' => [
                'fieldSorting' => new FieldSorting('product.name', 'DESC'),
                'expectedOrder' => 'label DESC'
            ],
            'PriceSorting is ASC' => [
                'fieldSorting' => new FieldSorting('product.listingPrices', 'ASC'),
                'expectedOrder' => 'price ASC'
            ],
            'PriceSorting is DESC' => [
                'fieldSorting' => new FieldSorting('product.listingPrices', 'DESC'),
                'expectedOrder' => 'price DESC'
            ],
            'ScoreSorting is ASC' => [
                'fieldSorting' => new FieldSorting('_score', 'ASC'),
                'expectedOrder' => '' // Order generated by customer login.
            ],
            'ScoreSorting is DESC' => [
                'fieldSorting' => new FieldSorting('_score', 'DESC'),
                'expectedOrder' => '' // Order generated by customer login.
            ],
            'ReleaseDateSorting is ASC' => [
                'fieldSorting' => new FieldSorting('product.dateadded', 'ASC'),
                'expectedOrder' => '' // Currently not supported by Shopware.
            ],
            'ReleaseDateSorting is DESC' => [
                'fieldSorting' => new FieldSorting('product.dateadded', 'DESC'),
                'expectedOrder' => '' // Currently not supported by Shopware.
            ],
        ];
    }

    /**
     * @dataProvider sortingProvider
     *
     * @param FieldSorting $fieldSorting
     * @param string $expectedOrder
     */
    public function testSortingIsSubmitted(FieldSorting $fieldSorting, string $expectedOrder): void
    {
        $eventMock = $this->setUpSearchRequestMocks($this->getDefaultResponse());

        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

        $criteriaMock->expects($this->any())->method('getSorting')->willReturn([$fieldSorting]);

        $searchRequest = new SearchRequest();
        $this->searchRequestFactoryMock->expects($this->any())
            ->method('getInstance')
            ->willReturn($searchRequest);

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleSearchRequest($eventMock);

        if ($expectedOrder !== '') {
            $this->assertEquals($expectedOrder, $searchRequest->getParams()['order']);
        } else {
            $this->assertArrayNotHasKey('order', $searchRequest->getParams());
        }
    }

    /**
     * @dataProvider requestProvider
     *
     * @param string $endpoint
     * @param array $expectedProducts
     * @param bool $isNavigationRequest
     */
    public function testServiceNotAliveExceptionsAreCaught(
        string $endpoint,
        array $expectedProducts,
        bool $isNavigationRequest
    ): void {
        $eventMock = $this->setUpSearchRequestMocks();

        $this->apiClientMock->expects($this->any())->method('send')->willThrowException(
            new ServiceNotAliveException('dead: This service is currently unreachable.')
        );

        /** @var Criteria|MockObject $criteriaMock */
        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);
        $criteriaMock->expects($this->any())->method('assign')->with([]); // Should be empty.

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->{$endpoint}($eventMock);
    }

    public function testResponseHasPromotion(): void
    {
        $eventMock = $this->setUpSearchRequestMocks($this->getDefaultResponse());

        $findologicEnabledMock = $this->getMockBuilder(FindologicEnabled::class)
            ->disableOriginalConstructor()
            ->getMock();
        $findologicEnabledMock->expects($this->any())->method('getEnabled')->willReturn(true);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicEnabledMock);
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['flEnabled'],
            ['flSmartDidYouMean'],
            ['flPromotion', new Promotion('https://promotion.com/promotion.png', 'https://promotion.com/')]
        );
        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);

        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleSearchRequest($eventMock);
    }

    public function testResponseHasNoPromotion(): void
    {
        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse();
        unset($response->promotion);

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($response->asXML()));

        $findologicEnabledMock = $this->getMockBuilder(FindologicEnabled::class)
            ->disableOriginalConstructor()
            ->getMock();
        $findologicEnabledMock->expects($this->any())->method('getEnabled')->willReturn(true);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicEnabledMock);
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['flEnabled'],
            ['flSmartDidYouMean']
        );
        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);

        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleSearchRequest($eventMock);
    }

    public function testContainsDidYouMeanQuery(): void
    {
        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse('demoResponseWithDidYouMeanQuery.xml');

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($response->asXML()));

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
                    new Query($this->buildSmartDidYouMeanQueryElement('ps4')),
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

    public function testContainsCorrectedQuery(): void
    {
        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse('demoResponseWithCorrectedQuery.xml');

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($response->asXML()));

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
                    new Query($this->buildSmartDidYouMeanQueryElement(null, null, 'ps4')),
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

    public function testContainsImprovedQuery(): void
    {
        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse('demoResponseWithImprovedQuery.xml');

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($response->asXML()));

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
                    new Query($this->buildSmartDidYouMeanQueryElement(null, 'ps4')),
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

    public function queryInfoMessageProvider()
    {
        return [
            'Submitting an empty search' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => '', 'vendor' => ''],
                'alternativeQuery' => ''
            ],
            'Submitting an empty search with a selected category' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten', 'vendor' => ''],
                'alternativeQuery' => ''
            ],
            'Submitting an empty search with a selected sub-category' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten_Tees', 'vendor' => ''],
                'alternativeQuery' => ''
            ],
            'Submitting an empty search with a selected vendor' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => '', 'vendor' => 'Shopware Food'],
                'alternativeQuery' => ''
            ],
            'Submitting a search with some query' => [
                'queryString' => 'some query',
                'queryStringType' => null,
                'params' => ['cat' => '', 'vendor' => ''],
                'alternativeQuery' => 'some query'
            ],
            'Submitting a search with some query and a selected category and vendor filter' => [
                'queryString' => 'some query',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten', 'vendor' => 'Shopware Food'],
                'alternativeQuery' => 'some query'
            ],
            'Submitting a search where the response will have an improved query' => [
                'queryString' => 'special',
                'queryStringType' => 'improved',
                'params' => ['cat' => '', 'vendor' => ''],
                'alternativeQuery' => 'very special'
            ],
            'Submitting a search where the response will have a corrected query' => [
                'queryString' => 'standord',
                'queryStringType' => 'improved',
                'params' => ['cat' => '', 'vendor' => ''],
                'alternativeQuery' => 'standard'
            ],
        ];
    }

    /**
     * @dataProvider queryInfoMessageProvider
     * @param string[] $params
     */
    public function testQueryInfoMessage(
        string $queryString,
        ?string $queryStringType,
        array $params,
        string $alternativeQuery
    ): void {
        $this->configMock->expects($this->once())->method('isActive')->willReturn(true);
        $xmlResponse = clone $this->getRawResponse();
        unset($xmlResponse->query);

        $query = $xmlResponse->addChild('query');
        $limit = $query->addChild('limit');
        $limit->addAttribute('first', '0');
        $limit->addAttribute('count', '24');
        $queryStringXml = $query->addChild('queryString', $queryString);
        if ($queryStringType !== null) {
            $queryStringXml->addAttribute('type', $queryStringType);
        }

        $request = new Request();
        foreach ($params as $key => $param) {
            $request->query->set($key, $param);
        }

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($xmlResponse->asXML()), $request);
        $eventMock->expects($this->any())->method('getRequest')->willReturn($request);
        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleSearchRequest($eventMock);
    }
}
