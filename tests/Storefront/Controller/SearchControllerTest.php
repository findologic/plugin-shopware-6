<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Storefront\Controller;

use FINDOLOGIC\Api\Responses\Json10\Json10Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10ResponseParser;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21ResponseParser;
use FINDOLOGIC\FinSearch\Storefront\Controller\SearchController;
use FINDOLOGIC\FinSearch\Storefront\Page\Search\SearchPageLoader;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\MockResponseHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\WithTestClient;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\SearchController as ShopwareSearchController;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

use function json_decode;

class SearchControllerTest extends TestCase
{
    use WithTestClient;
    use SalesChannelHelper;
    use PluginConfigHelper;
    use StorefrontControllerTestBehaviour;
    use MockResponseHelper;

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->buildAndCreateSalesChannelContext();
    }

    public static function availableFilterProvider(): array
    {
        return [
            'Available filters are returned in response' => [
                'demoResponse' => 'JSONResponse/demo.json',
                'expectedResponse' => 'JSONResponse/availableFilterResponse.json'
            ],
            'Empty category values are not returned in response' => [
                'demoResponse' => 'JSONResponse/demoResponseWithoutCategoryFilter.json',
                'expectedResponse' => 'JSONResponse/availableFilterResponseWithoutCategory.json'
            ]
        ];
    }

    /**
     * @dataProvider availableFilterProvider
     */
    public function testAvailableFilterReturnsCorrectResponse(string $demoResponse, string $expectedResponse): void
    {
        $response = new Json10Response($this->getMockResponse($demoResponse));
        $parser = new Json10ResponseParser($response);
        $filterExtension = $parser->getFiltersExtension();

        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request();
        $criteria = new Criteria();
        $criteria->setExtensions(['flAvailableFilters' => $filterExtension, 'flFilters' => $filterExtension]);
        $eventMock->method('getRequest')->willReturn($request);
        $eventMock->method('getCriteria')->willReturn($criteria);

        $filterHandler = new FilterHandler();
        $filterResponse = $filterHandler->handleAvailableFilters($eventMock);
        $expectedFilters = json_decode($this->getMockResponse($expectedResponse), true);

        $this->assertSame($filterResponse, $expectedFilters);
    }

    public function testFiltersWhichAreNotInTheAvailableFilterResponseAreStillReturned(): void
    {
        $availableFiltersResponse = new Json10Response(
            $this->getMockResponse('JSONResponse/demoResponseWithNoResults.json')
        );
        $responseParser = new Json10ResponseParser($availableFiltersResponse);
        $availableFilters = $responseParser->getFiltersExtension();

        $allFiltersResponse = new Json10Response(
            $this->getMockResponse('JSONResponse/demoResponseWithAllFilterTypes.json')
        );
        $parser = new Json10ResponseParser($allFiltersResponse);
        $allFilters = $parser->getFiltersExtension();

        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request();
        $criteria = new Criteria();
        $criteria->setExtensions(['flAvailableFilters' => $availableFilters, 'flFilters' => $allFilters]);
        $eventMock->method('getRequest')->willReturn($request);
        $eventMock->method('getCriteria')->willReturn($criteria);

        $filterHandler = new FilterHandler();
        $filterResponse = $filterHandler->handleAvailableFilters($eventMock);
        $expectedFilters = [
            'properties' => [
                'entities' => [
                    'rating' => [
                        'max' => 0,
                        'entities' => []
                    ],
                    'cat' => [
                        'entities' => []
                    ],
                    'vendor' => [
                        'entities' => []
                    ],
                    'price' => [
                        'entities' => []
                    ],
                    'Farbe' => [
                        'entities' => []
                    ],
                    'Material' => [
                        'entities' => []
                    ],
                ]
            ],
            'rating' => [
                'max' => 0,
                'entities' => []
            ],
            'cat' => [
                'entities' => []
            ],
            'vendor' => [
                'entities' => []
            ],
            'price' => [
                'entities' => []
            ],
            'Farbe' => [
                'entities' => []
            ],
            'Material' => [
                'entities' => []
            ],
        ];

        $this->assertSame($filterResponse, $expectedFilters);
    }

    public function testShopwareSearchControllerIsUsedForFilterActionWhenFindologicIsDisabled(): void
    {
        $findologicService = new FindologicService();
        $findologicService->disable();
        $this->salesChannelContext->getContext()->addExtension('findologicService', $findologicService);

        $shopwareSearchControllerMock = $this->getMockBuilder(ShopwareSearchController::class)
            ->disableOriginalConstructor()
            ->getMock();
        $shopwareSearchControllerMock->expects($this->once())->method('filter');

        $searchPageLoaderMock = $this->getMockBuilder(SearchPageLoader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $filterHandlerMock = $this->getMockBuilder(FilterHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $filterHandlerMock->expects($this->never())->method('handleAvailableFilters');

        $findologicSearchServiceMock = $this->getMockBuilder(FindologicSearchService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $serviceConfigResource = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();

        $findologicConfigServiceMock = $this->getMockBuilder(FindologicConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $searchController = new SearchController(
            $shopwareSearchControllerMock,
            $filterHandlerMock,
            $findologicSearchServiceMock,
            $serviceConfigResource,
            $searchPageLoaderMock,
            $this->getContainer(),
            $findologicConfigServiceMock
        );

        $searchController->filter(new Request(), $this->salesChannelContext);
    }
}
