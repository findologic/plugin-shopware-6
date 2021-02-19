<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Storefront\Controller;

use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21ResponseParser;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\MockResponseHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\WithTestClient;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

    /** @var SalesChannelContext */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function availableFilterProvider()
    {
        return [
            'Available filters are returned in response' => [
                'demoResponse' => 'XMLResponse/demoResponseWithAvailableFilters.xml',
                'expectedResponse' => 'JSONResponse/availableFilterResponse.json'
            ],
            'Empty category values are not returned in response' => [
                'demoResponse' => 'XMLResponse/demoResponseWithAvailableFiltersWithoutCategory.xml',
                'expectedResponse' => 'JSONResponse/availableFilterResponseWithoutCategory.json'
            ]
        ];
    }
    /**
     * @dataProvider availableFilterProvider
     */
    public function testAvailableFilterReturnsCorrectResponse(string $demoResponse, string $expectedResponse): void
    {
        if (Utils::versionLowerThan('6.3.3.0')) {
            $this->markTestSkipped('Filter disabling feature was introduced in Shopware 6.3.3.0');
        }

        $response = new Xml21Response($this->getMockResponse($demoResponse));
        $parser = new Xml21ResponseParser($response);
        $filterExtension = $parser->getFiltersExtension();

        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request();
        $criteria = new Criteria();
        $criteria->setExtensions(['flAvailableFilters' => $filterExtension]);
        $eventMock->method('getRequest')->willReturn($request);
        $eventMock->method('getCriteria')->willReturn($criteria);

        $filterHandler = new FilterHandler();
        $filterResponse = $filterHandler->handleAvailableFilters($eventMock);
        $expectedFilters = json_decode($this->getMockResponse($expectedResponse), true);

        $this->assertSame($filterResponse, $expectedFilters);
    }
}
