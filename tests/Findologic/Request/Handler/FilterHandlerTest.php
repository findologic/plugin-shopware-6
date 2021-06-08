<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request\Handler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\ColorPickerFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\ColorFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;

class FilterHandlerTest extends TestCase
{
    public function filterRequestProvider(): array
    {
        return [
            'Filter name with special characters' => [
                'filterName' => 'filter-name',
                'filterValue' => sprintf('filter-name%ssomevalue', FilterValue::DELIMITER),
                'expectedValue' => 'somevalue'
            ],
            'Filter name with spaces' => [
                'filterName' => 'filter name',
                'filterValue' => sprintf('filter name%ssomevalue', FilterValue::DELIMITER),
                'expectedValue' => 'somevalue'
            ],
            'Filter value with special characters' => [
                'filterName' => 'filtername',
                'filterValue' => sprintf('filtername%ssome-value', FilterValue::DELIMITER),
                'expectedValue' => 'some-value'
            ],
            'Filter value with space' => [
                'filterName' => 'filtername',
                'filterValue' => sprintf('filtername%ssome value', FilterValue::DELIMITER),
                'expectedValue' => 'some value'
            ]
        ];
    }

    /**
     * @dataProvider filterRequestProvider
     */
    public function testFilters(string $filterName, string $filterValue, string $expectedValue): void
    {
        $searchNavigationRequest = new SearchRequest();

        $colorFilterValue = new ColorFilterValue($filterValue, $filterValue, $filterName);
        $colorFilterValue->setColorHexCode('#3c6380');
        $expectedColorFilter = new ColorPickerFilter($filterName, $filterName);
        $expectedColorFilter->addValue($colorFilterValue);

        $filterExtension = new FiltersExtension([$expectedColorFilter]);

        $filterHandler = new FilterHandler();
        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request([$expectedColorFilter->getId() => $colorFilterValue->getUuid()]);
        $criteria = new Criteria();
        $criteria->setExtensions(['flFilters' => $filterExtension]);

        $eventMock->method('getRequest')->willReturn($request);
        $eventMock->method('getCriteria')->willReturn($criteria);
        $filterHandler->handleFilters($eventMock, $searchNavigationRequest);
        $result = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('attrib', $result);
        $this->assertArrayHasKey($filterName, $result['attrib']);
        $this->assertSame($expectedValue, current($result['attrib'][$filterName]));
    }

    public function testHandleFindologicSearchParams(): void
    {
        $filterHandler = new FilterHandler();
        $request = new Request([
            'search' => '',
            'attrib' => [
                'vendor' => ['shopware'],
                'cat' => ['Test_Test Sub']
            ]
        ]);

        $actualUrl = $filterHandler->handleFindologicSearchParams($request);
        $expectedUrl = '?search=&vendor=shopware&cat=Test_Test%20Sub';

        $this->assertEquals($expectedUrl, $actualUrl);
    }

    public function testPushAttribIsNotAddedAsRegularFilter(): void
    {
        $searchNavigationRequest = new SearchRequest();
        $filterHandler = new FilterHandler();
        $filterExtension = new FiltersExtension();

        $request = new Request([
            'search' => 'artikel',
            'pushAttrib' => [
                'size' => ['xl' => 30],
            ]
        ]);

        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $criteria = new Criteria();
        $criteria->setExtensions(['flFilters' => $filterExtension]);
        $eventMock->method('getRequest')->willReturn($request);
        $eventMock->method('getCriteria')->willReturn($criteria);

        $filterHandler->handleFilters($eventMock, $searchNavigationRequest);
        $result = $searchNavigationRequest->getParams();
        $this->assertEmpty($result);
    }
}
