<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber;
use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use FINDOLOGIC\FinSearch\Findologic\Api\PaginationService;
use FINDOLOGIC\FinSearch\Findologic\Api\SortingService;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\CategoryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\DefaultInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\SearchTermQueryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\VendorInfoMessage;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ExtensionHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber as
    ShopwareProductListingFeaturesSubscriber;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use SimpleXMLElement;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Tests in this test class act more like integration tests, as they mock the whole search stack.
 */
class ProductListingFeaturesSubscriberTest extends TestCase
{
    use ExtensionHelper;
    use ProductHelper;
    use IntegrationTestBehaviour;
    use SalesChannelHelper;

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

    /** @var FindologicConfigService|MockObject */
    private $findologicConfigServiceMock;

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

    /** @var EventDispatcherInterface|MockObject */
    private $eventDispatcherMock;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ProductSortingCollection */
    private $sortingCollection;

    public function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->initMocks();
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
     */
    public function testResponseMatchesProductIds(
        string $endpoint,
        array $expectedProducts,
        bool $isNavigationRequest
    ): void {
        if (!$isNavigationRequest) {
            $eventMock = $this->setUpSearchRequestMocks($this->getDefaultResponse());
        } else {
            $eventMock = $this->setUpNavigationRequestMocks();
        }

        $expectedAssign = [
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
            'inherited' => false,
            'term' => null,
            'extensions' => [
                'flPagination' => new Pagination(24, 0, 1808)
            ],
            'includes' => null,
        ];
        if (Utils::versionLowerThan('6.3')) {
            $expectedAssign['source'] = null;
        }
        if (Utils::versionLowerThan('6.4')) {
            $expectedAssign['states'] = [];
        }
        $expectedAssign['title'] = null;

        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $criteriaMock->expects($this->any())->method('assign')->with($expectedAssign);

        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

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
            'ProductSales is ASC' => [
                'fieldSorting' => new FieldSorting('product.sales', 'ASC'),
                'expectedOrder' => 'salesfrequency ASC'
            ],
            'ProductSales is DESC' => [
                'fieldSorting' => new FieldSorting('product.sales', 'DESC'),
                'expectedOrder' => 'salesfrequency DESC'
            ],
        ];
    }

    /**
     * @dataProvider sortingProvider
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
     */
    public function testServiceNotAliveExceptionsAreCaught(
        string $endpoint,
        array $expectedProducts,
        bool $isNavigationRequest
    ): void {
        $eventMock = $this->setUpSearchRequestMocks(null, null, false);

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

    public function promotionRequestProvider()
    {
        return [
            'Search response has promotion' => ['search' => true, 'endpoint' => 'handleSearchRequest'],
            'Navigation response has promotion' => ['search' => false, 'endpoint' => 'handleListingRequest'],
        ];
    }

    /**
     * @dataProvider promotionRequestProvider
     */
    public function testResponseHasPromotion(bool $isSearch, string $endpoint): void
    {
        if ($isSearch) {
            $eventMock = $this->setUpSearchRequestMocks($this->getDefaultResponse());
        } else {
            $eventMock = $this->setUpNavigationRequestMocks();
        }

        $findologicServiceMock = $this->getMockBuilder(FindologicService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $findologicServiceMock->expects($this->any())->method('getEnabled')->willReturn(true);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicServiceMock);
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['findologicService'],
            ['flSmartDidYouMean'],
            ['flPromotion', new Promotion('https://promotion.com/promotion.png', 'https://promotion.com/')]
        );
        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);

        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->{$endpoint}($eventMock);
    }

    public function testResponseHasNoPromotion(): void
    {
        $this->configMock->expects($this->any())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse();
        unset($response->promotion);

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($response->asXML()));

        $findologicServiceMock = $this->getMockBuilder(FindologicService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $findologicServiceMock->expects($this->any())->method('getEnabled')->willReturn(true);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicServiceMock);
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['findologicService'],
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
        $this->configMock->expects($this->any())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse('demoResponseWithDidYouMeanQuery.xml');

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($response->asXML()));

        $findologicServiceMock = $this->getMockBuilder(FindologicService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $findologicServiceMock->expects($this->any())->method('getEnabled')->willReturn(true);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicServiceMock);
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['findologicService'],
            [
                'flSmartDidYouMean',
                $this->getDefaultSmartDidYouMeanExtension('ps4', null, 'ps4')
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
        $this->configMock->expects($this->any())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse('demoResponseWithCorrectedQuery.xml');

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($response->asXML()), null, false);

        $findologicServiceMock = $this->getMockBuilder(FindologicService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $findologicServiceMock->expects($this->any())->method('getEnabled')->willReturn(true);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicServiceMock);
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['findologicService'],
            [
                'flSmartDidYouMean',
                $this->getDefaultSmartDidYouMeanExtension('', 'ps4', null, 'corrected')
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
        $this->configMock->expects($this->any())->method('isActive')->willReturn(true);
        $response = $this->getRawResponse('demoResponseWithImprovedQuery.xml');

        $eventMock = $this->setUpSearchRequestMocks(new Xml21Response($response->asXML()), null, false);

        $findologicServiceMock = $this->getMockBuilder(FindologicService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $findologicServiceMock->expects($this->any())->method('getEnabled')->willReturn(true);

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturn($findologicServiceMock);
        $contextMock->expects($this->any())->method('addExtension')->withConsecutive(
            ['findologicService'],
            [
                'flSmartDidYouMean',
                $this->getDefaultSmartDidYouMeanExtension('', 'ps4', null, 'improved')
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
                'expectedInstance' => DefaultInfoMessage::class
            ],
            'Submitting an empty search with a selected category' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten', 'vendor' => ''],
                'expectedInstance' => CategoryInfoMessage::class
            ],
            'Submitting an empty search with a selected sub-category' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten_Tees', 'vendor' => ''],
                'expectedInstance' => CategoryInfoMessage::class
            ],
            'Submitting an empty search with a selected vendor' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => '', 'vendor' => 'Shopware Food'],
                'expectedInstance' => VendorInfoMessage::class
            ],
            'Submitting a search with some query' => [
                'queryString' => 'some query',
                'queryStringType' => null,
                'params' => ['cat' => '', 'vendor' => ''],
                'expectedInstance' => SearchTermQueryInfoMessage::class
            ],
            'Submitting a search with some query and a selected category and vendor filter' => [
                'queryString' => 'some query',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten', 'vendor' => 'Shopware Food'],
                'expectedInstance' => SearchTermQueryInfoMessage::class
            ],
            'Submitting a search where the response will have an improved query' => [
                'queryString' => 'special',
                'queryStringType' => 'improved',
                'params' => ['cat' => '', 'vendor' => ''],
                'expectedInstance' => SearchTermQueryInfoMessage::class
            ],
            'Submitting a search where the response will have a corrected query' => [
                'queryString' => 'standord',
                'queryStringType' => 'improved',
                'params' => ['cat' => '', 'vendor' => ''],
                'expectedInstance' => SearchTermQueryInfoMessage::class
            ],
        ];
    }

    /**
     * @dataProvider queryInfoMessageProvider
     *
     * @param array<string, string|array<string, string>> $params
     */
    public function testQueryInfoMessage(
        string $queryString,
        ?string $queryStringType,
        array $params,
        string $expectedInstance
    ): void {
        $this->configMock->expects($this->any())->method('isActive')->willReturn(true);
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
        $context = Context::createDefaultContext();

        $request->setSession($this->getDefaultSessionMock());
        $eventMock = $this->setUpSearchRequestMocks(
            new Xml21Response($xmlResponse->asXML()),
            $request,
            false,
            $context
        );
        $eventMock->expects($this->any())->method('getRequest')->willReturn($request);
        $criteria = new Criteria();
        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteria);

        $eventMock->expects($this->any())->method('getContext')->willReturn($context);

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleSearchRequest($eventMock);

        $this->assertInstanceOf($expectedInstance, $context->getExtension('flQueryInfoMessage'));
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

        // Sorting is handled via database since Shopware 6.3.2.
        if (!Utils::versionLowerThan('6.3.2')) {
            $sorting = new ProductSortingEntity();
            $sorting->setId('score');
            $sorting->setKey('score');
            $sorting->setFields(['score' => ['field' => '_score', 'order' => 'asc']]);
            $sorting->setUniqueIdentifier('score');
            $this->sortingCollection = new ProductSortingCollection([$sorting]);

            if (method_exists($this->productListingSortingRegistry, 'getProductSortingEntities')) {
                $this->productListingSortingRegistry->expects($this->any())
                    ->method('getProductSortingEntities')
                    ->willReturn($this->sortingCollection);
            }

            $this->entityRepositoryMock->expects($this->any())->method('search')->willReturn(
                Utils::buildEntitySearchResult(
                    ProductSortingEntity::class,
                    $this->sortingCollection->count(),
                    $this->sortingCollection,
                    new AggregationResultCollection(),
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );
        }
        $this->navigationRequestFactoryMock = $this->getMockBuilder(NavigationRequestFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->searchRequestFactoryMock = $this->getMockBuilder(SearchRequestFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->systemConfigServiceMock = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->findologicConfigServiceMock = $this->getMockBuilder(FindologicConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->serviceConfigResourceMock = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->genericPageLoaderMock = $this->getMockBuilder(GenericPageLoader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->containerMock = $this->getMockBuilder(Container::class)->disableOriginalConstructor()->getMock();
        $this->containerMock->method('getParameter')->with('kernel.shopware_version')->willReturn('6.3');

        $this->configMock = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $this->configMock->expects($this->any())->method('isInitialized')->willReturn(true);
        $this->apiConfigMock = $this->getMockBuilder(ApiConfig::class)->disableOriginalConstructor()->getMock();
        $this->apiClientMock = $this->getMockBuilder(ApiClient::class)->disableOriginalConstructor()->getMock();
        $this->eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->containerMock->expects($this->any())->method('get')->willReturnCallback(function ($name) {
            switch ($name) {
                case 'event_dispatcher':
                    return $this->eventDispatcherMock;
                case 'category.repository':
                    return $this->getContainer()->get('category.repository');
                case ServiceConfigResource::class:
                    return $this->serviceConfigResourceMock;
                case SearchRequestFactory::class:
                    return $this->searchRequestFactoryMock;
                case NavigationRequestFactory::class:
                    return $this->navigationRequestFactoryMock;
                case 'translator':
                    return $this->getContainer()->get('translator');
                default:
                    return null;
            }
        });
    }

    /**
     * @return ProductListingFeaturesSubscriber
     */
    private function getDefaultProductListingFeaturesSubscriber()
    {
        if (Utils::versionLowerThan('6.3.2')) {
            $shopwareProductListingFeaturesSubscriber = new ShopwareProductListingFeaturesSubscriber(
                $this->connectionMock,
                $this->entityRepositoryMock,
                $this->productListingSortingRegistry
            );
        } elseif (Utils::versionLowerThan('6.4')) {
            $shopwareProductListingFeaturesSubscriber = new ShopwareProductListingFeaturesSubscriber(
                $this->connectionMock,
                $this->entityRepositoryMock,
                $this->entityRepositoryMock,
                $this->systemConfigServiceMock,
                $this->productListingSortingRegistry,
                $this->eventDispatcherMock
            );
        } else {
            $shopwareProductListingFeaturesSubscriber = new ShopwareProductListingFeaturesSubscriber(
                $this->connectionMock,
                $this->entityRepositoryMock,
                $this->entityRepositoryMock,
                $this->systemConfigServiceMock,
                $this->eventDispatcherMock
            );
        }

        $sortingService = new SortingService(
            $this->productListingSortingRegistry,
            $this->getContainer()->get('translator')
        );
        $paginationService = new PaginationService();

        $findologicSearchService = new FindologicSearchService(
            $this->containerMock,
            $this->apiClientMock,
            $this->apiConfigMock,
            $this->configMock,
            $this->genericPageLoaderMock,
            $sortingService,
            $paginationService
        );

        return new ProductListingFeaturesSubscriber(
            $shopwareProductListingFeaturesSubscriber,
            $findologicSearchService
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
        $sessionMock = $this->getDefaultSessionMock();
        $requestMock->expects($this->any())->method('getSession')->willReturn($sessionMock);

        $queryMock = $this->getMockBuilder(ParameterBag::class)->getMock();
        $queryMock->expects($this->any())
            ->method('getInt')
            ->willReturnOnConsecutiveCalls(24, 1);
        $queryMock->expects($this->any())->method('get')->willReturn('');
        $queryMock->expects($this->any())->method('all')->willReturn([]);

        $requestMock->expects($this->any())->method('get')->willReturnCallback(function ($name) {
            switch ($name) {
                case 'availableSortings':
                    return ['score' => ['field' => '_score', 'order' => 'asc']];
                case 'navigationId':
                    return null;
                default:
                    return 'score';
            }
        });

        $requestMock->query = $queryMock;
        $requestMock->request = $queryMock;
        $requestMock->headers = new ParameterBag();

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
     * @return MockObject|ProductSearchCriteriaEvent
     */
    private function setUpSearchRequestMocks(
        ?Xml21Response $response = null,
        ?Request $request = null,
        bool $withSmartDidYouMean = true,
        Context $context = null
    ): ProductSearchCriteriaEvent {
        $this->setUpCategoryRepositoryMock();

        $this->configMock->expects($this->any())->method('isActive')->willReturn(true);
        $this->configMock->expects($this->any())->method('getShopkey')
            ->willReturn('ABCDABCDABCDABCDABCDABCDABCDABCD');
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

        $findologicService = new FindologicService();
        $smartDidYouMean = $this->getDefaultSmartDidYouMeanExtension();
        $defaultExtensionMap = [
            ['findologicService', $findologicService],
            ['flSmartDidYouMean', $smartDidYouMean],
        ];

        if (!$context) {
            $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
            $context->expects($this->any())->method('getExtension')->willReturnMap($defaultExtensionMap);

            if ($withSmartDidYouMean) {
                $context->expects($this->any())->method('addExtension')->withConsecutive(
                    ['findologicService', $findologicService],
                    ['flSmartDidYouMean', $smartDidYouMean]
                );
            } else {
                $context->expects($this->any())->method('addExtension')->withConsecutive(
                    ['findologicService', $findologicService]
                );
            }
        }

        $eventMock->expects($this->any())->method('getContext')->willReturn($context);

        return $eventMock;
    }

    /**
     * @return EntityRepository|MockObject
     */
    private function setUpCategoryRepositoryMock(): EntityRepository
    {
        $categoryMock = $this->getMockBuilder(CategoryEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $categoryCollectionMock = $this->getMockBuilder(EntitySearchResult::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityRepoMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityRepoMock->expects($this->any())->method('search')->willReturn($categoryCollectionMock);
        $categoryCollectionMock->expects($this->any())->method('get')->willReturn($categoryMock);

        $this->containerMock->expects($this->any())->method('get')
            ->willReturnCallback(
                function (string $name) use ($entityRepoMock) {
                    if ($name === 'category.repository') {
                        return $entityRepoMock;
                    }
                    if ($name === ServiceConfigResource::class) {
                        return $this->getContainer()->get(ServiceConfigResource::class);
                    }
                    if ($name === SearchRequestFactory::class) {
                        return $this->searchRequestFactoryMock;
                    }
                    if ($name === NavigationRequestFactory::class) {
                        return $this->navigationRequestFactoryMock;
                    }

                    return null;
                }
            );

        return $entityRepoMock;
    }

    /**
     * @return ProductListingCriteriaEvent|MockObject
     */
    private function setUpNavigationRequestMocks(
        ?CategoryEntity $category = null,
        ?array $extensionMapOverride = null
    ): ProductListingCriteriaEvent {
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
        $headerMock->expects($this->any())->method('getNavigation')->willReturn($categoryTreeMock);

        if (!$category) {
            $category = $this->getMockBuilder(CategoryEntity::class)
                ->disableOriginalConstructor()
                ->getMock();

            $category->expects($this->any())->method('getBreadcrumb')
                ->willReturn(['Deutsch', 'Freizeit & Elektro']);
        }

        $categoryTreeMock->expects($this->any())->method('getActive')->willReturn($category);

        /** @var ProductListingCriteriaEvent|MockObject $eventMock */
        $eventMock = $this->getMockBuilder(ProductListingCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request = $this->getDefaultRequestMock();
        $eventMock->expects($this->any())->method('getRequest')->willReturn($request);

        $findologicService = new FindologicService();
        $smartDidYouMean = $this->getDefaultSmartDidYouMeanExtension();

        $defaultExtensionMap = $extensionMapOverride;
        if ($defaultExtensionMap === null) {
            $defaultExtensionMap = [
                ['findologicService', $findologicService],
                ['flSmartDidYouMean', $smartDidYouMean]
            ];
        }

        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $contextMock->expects($this->any())->method('getExtension')->willReturnMap($defaultExtensionMap);
        $eventMock->expects($this->any())->method('getContext')->willReturn($contextMock);

        return $eventMock;
    }

    private function getDefaultSessionMock(): SessionInterface
    {
        /** @var SessionInterface|MockObject $sessionMock */
        $sessionMock = $this->getMockBuilder(SessionInterface::class)->disableOriginalConstructor()->getMock();
        $sessionMock->expects($this->any())->method('get')->with('stagingFlag')->willReturn(false);

        return $sessionMock;
    }

    public function criteriaLimitProvider(): array
    {
        return [
            'search request with custom limit' => [
                'endpoint' => 'handleSearchRequest',
                'expectedLimit' => 3,
                'isNavigationRequest' => false
            ],
            'navigation request with custom limit' => [
                'endpoint' => 'handleListingRequest',
                'expectedLimit' => 3,
                'isNavigationRequest' => true
            ],
            'search request with default limit' => [
                'endpoint' => 'handleSearchRequest',
                'expectedLimit' => 24,
                'isNavigationRequest' => false
            ],
            'navigation request with default limit' => [
                'endpoint' => 'handleListingRequest',
                'expectedLimit' => 24,
                'isNavigationRequest' => true
            ],
            'search request with custom higher limit' => [
                'endpoint' => 'handleSearchRequest',
                'expectedLimit' => 35,
                'isNavigationRequest' => false
            ],
            'navigation request with custom higher limit' => [
                'endpoint' => 'handleListingRequest',
                'expectedLimit' => 35,
                'isNavigationRequest' => true
            ],
        ];
    }

    /**
     * @dataProvider criteriaLimitProvider
     */
    public function testCriteriaLimitIsSetForPagination(
        string $endpoint,
        int $expectedLimit,
        bool $isNavigationRequest
    ): void {
        if (!$isNavigationRequest) {
            $eventMock = $this->setUpSearchRequestMocks($this->getDefaultResponse());
        } else {
            $eventMock = $this->setUpNavigationRequestMocks();
        }

        $expectedAssign = [
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
            'ids' => [
                '019111105-37900' => '019111105-37900',
                '029214085-37860' => '029214085-37860'
            ],
            'inherited' => false,
            'term' => null,
            'extensions' => [
                'flPagination' => new Pagination($expectedLimit, 0, 1808)
            ],
            'includes' => null
        ];
        if (Utils::versionLowerThan('6.3')) {
            $expectedAssign['source'] = null;
        }
        if (Utils::versionLowerThan('6.4')) {
            $expectedAssign['states'] = [];
        }
        $expectedAssign['title'] = null;
        $expectedAssign['limit'] = $expectedLimit;

        $criteriaMock = $this->getMockBuilder(Criteria::class)->disableOriginalConstructor()->getMock();
        $invokeCountAssign = $isNavigationRequest ? $this->never() : $this->once();
        $invokeCountOffset = $isNavigationRequest ? $this->never() : $this->exactly(3);
        $criteriaMock->expects($invokeCountAssign)->method('assign')->with($expectedAssign);
        $criteriaMock->expects($invokeCountOffset)->method('getOffset')->willReturn(0);
        // Add this check to ensure that if no custom limit is provided, it uses the default limit
        $criteriaMock->expects($this->any())->method('getLimit')->willReturn($expectedLimit);

        $eventMock->expects($this->any())->method('getCriteria')->willReturn($criteriaMock);

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->{$endpoint}($eventMock);
    }

    public function testListingRequestIsNotHandledWhenDeepCategoryIsMainCategory(): void
    {
        $this->configMock->expects($this->any())->method('getShopkey')
            ->willReturn('ABCDABCDABCDABCDABCDABCDABCDABCD');
        $this->configMock->expects($this->any())->method('isActive')->willReturn(true);
        $this->configMock->expects($this->any())->method('isActiveOnCategoryPages')->willReturn(true);
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rootCategoryMock = $this->getMockBuilder(CategoryEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rootCategoryMock->expects($this->any())->method('getId')->willReturn(Uuid::randomHex());

        $rootCategoryMock->expects($this->any())->method('getBreadcrumb')
            ->willReturn(['FINDOLOGIC Main 2', 'FINDOLOGIC Sub', 'Very deep']);

        $salesChannelMock->expects($this->once())->method('getNavigationCategory')
            ->willReturn($rootCategoryMock);

        $salesChannelContextMock->expects($this->any())->method('getSalesChannel')
            ->willReturn($salesChannelMock);

        $this->createTestProduct();
        $oneSubCategoryFilter = new Criteria();
        $oneSubCategoryFilter->addFilter(new EqualsFilter('name', 'Very deep'))->setLimit(1);

        $categoryRepo = $this->getContainer()->get('category.repository');
        $category = $categoryRepo->search($oneSubCategoryFilter, Context::createDefaultContext())->first();

        $eventMock = $this->setUpNavigationRequestMocks($category);
        $eventMock->expects($this->any())->method('getSalesChannelContext')
            ->willReturn($salesChannelContextMock);

        /** @var Request|MockObject $requestMock */
        $requestMock = $eventMock->getRequest();
        $requestMock->expects($this->any())->method('get')
            ->willReturnCallback(function ($param) {
                if ($param === 'navigationId') {
                    return Uuid::randomHex();
                }

                return null;
            });

        $criteriaBefore = $eventMock->getCriteria()->cloneForRead();
        $contextBefore = clone $eventMock->getContext();

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleListingRequest($eventMock);

        // Ensure that Context and Criteria were not changed. Shopware handles this request.
        $this->assertEquals($criteriaBefore, $eventMock->getCriteria());
        $this->assertEquals($contextBefore, $eventMock->getContext());
    }

    public function testHandleResultDoesNotThrowExceptionWhenCalledManually(): void
    {
        if (Utils::versionLowerThan('6.3.3') && !Utils::versionLowerThan('6.3.1')) {
            $this->markTestSkipped('Shopware sorting bug prevents this from properly working.');
        }

        $this->initMocks();

        $criteria = new Criteria();
        if (!Utils::versionLowerThan('6.3.2')) {
            $criteria->addExtension('sortings', $this->sortingCollection);
        }

        $rawResult = Utils::buildEntitySearchResult(
            ProductEntity::class,
            0,
            new EntityCollection(),
            new AggregationResultCollection(),
            $criteria,
            Context::createDefaultContext()
        );

        $result = ProductListingResult::createFrom($rawResult);

        $orderParam = Utils::versionLowerThan('6.2') ? 'sort' : 'order';

        $event = new ProductListingResultEvent(
            new Request([$orderParam => 'score']),
            $result,
            $this->buildSalesChannelContext(Defaults::SALES_CHANNEL, 'http://test.de')
        );

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleResult($event);
    }

    public function testHandleListingRequestDoesNotThrowAnExceptionWhenCalledManuallyOnANonCategoryPage(): void
    {
        $addExtensionWasCalled = false;

        $this->configMock->expects($this->any())->method('getShopkey')
            ->willReturn('ABCDABCDABCDABCDABCDABCDABCDABCD');
        $this->configMock->expects($this->any())->method('isActive')->willReturn(true);
        $this->configMock->expects($this->any())->method('isActiveOnCategoryPages')->willReturn(true);
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock = $this->setUpNavigationRequestMocks(null, [
            ['findologicService', null],
            ['flSmartDidYouMean', $this->getDefaultSmartDidYouMeanExtension()]
        ]);
        $eventMock->expects($this->any())->method('getSalesChannelContext')
            ->willReturn($salesChannelContextMock);

        /** @var Context|MockObject $contextMock */
        $contextMock = $eventMock->getContext();
        $contextMock->expects($this->any())->method('addExtension')
            ->willReturnCallback(function (string $name, $value) use (&$addExtensionWasCalled) {
                if ($name === 'findologicService') {
                    $addExtensionWasCalled = true;
                    $this->assertEquals(new FindologicService(), $value);
                }
            });

        /** @var Request|MockObject $requestMock */
        $requestMock = $eventMock->getRequest();
        $requestMock->expects($this->any())->method('get')
            ->willReturnCallback(function ($param) {
                if ($param === 'navigationId') {
                    return Uuid::randomHex();
                }

                return null;
            });

        $subscriber = $this->getDefaultProductListingFeaturesSubscriber();
        $subscriber->handleListingRequest($eventMock);

        $this->assertTrue($addExtensionWasCalled);
    }
}
