<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\ColorPickerFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Media;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\ColorFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

class FilterHandlerTest extends TestCase
{
    public function filterRequestProvider(): array
    {
        return [
            'Filter name with special characters' => [
                'filter-name',
                sprintf('filter-name%somevalue', FilterValue::DELIMITER)
            ],
            'Filter name with spaces' => [
                'filter name',
                sprintf('filter name%somevalue', FilterValue::DELIMITER)
            ],
            'Filter value with special characters' => [
                'filtername',
                sprintf('filtername%some-value', FilterValue::DELIMITER)
            ],
            'Filter value with space' => [
                'filtername',
                sprintf('filtername%some value', FilterValue::DELIMITER)
            ]
        ];
    }

    /**
     * @dataProvider filterRequestProvider
     */
    public function testFilters(string $filterName, string $filterValue)
    {
        $searchNavigationRequest = new SearchRequest();
        $expectedColorFilter = new ColorPickerFilter($filterName, $filterName);
        $expectedColorFilter->addValue(
            (new ColorFilterValue($filterValue, $filterValue, $filterName))
                ->setColorHexCode('#3c6380')
                ->setMedia(new Media('https://blubbergurken.io/farbfilter/blau.gif'))
        );

        $filterExtension = new FiltersExtension([
            $expectedColorFilter
        ]);

        $filterHandler = new FilterHandler();
        /** @var ShopwareEvent|MockObject $event */
        $eventMock = $this->getMockBuilder(ProductSearchCriteriaEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request([$filterName => $filterValue]);
        $criteria = new Criteria();
        $criteria->setExtensions(['flFilters' => $filterExtension]);

        $eventMock->method('getRequest')->willReturn($request);
        $eventMock->method('getCriteria')->willReturn($criteria);
        $filterHandler->handleFilters($eventMock, $searchNavigationRequest);
        $result = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('attrib', $result);
        $this->assertArrayHasKey($filterName, $result['attrib']);
        $this->assertSame($filterValue, current($result['attrib'][$filterName]));
    }
}
