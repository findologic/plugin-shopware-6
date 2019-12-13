<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\Api\Responses\Xml21\Properties\OriginalQuery;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Query;
use FINDOLOGIC\Api\Responses\Xml21\Properties\QueryString;
use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SmartDidYouMeanTest extends TestCase
{
    public function testGeneratedLinkWhenQueryTypeIsDidYouMean(): void
    {
        $controllerPath = 'https://local.shopware';
        $didYouMeanQuery = 'FINDOLOGIC';
        $expectedLink = sprintf(
            '%s?search=%s&forceOriginalQuery=1',
            $controllerPath,
            $didYouMeanQuery
        );

        /** @var Query|MockObject $mockQuery */
        $mockQuery = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $mockQuery->expects($this->once())->method('getDidYouMeanQuery')->willReturn($didYouMeanQuery);
        $smartDidYouMean = new SmartDidYouMean($mockQuery, $controllerPath);
        $this->assertSame($expectedLink, $smartDidYouMean->getLink());
    }

    public function testGeneratedLinkWhenQueryTypeIsImproved(): void
    {
        $controllerPath = 'https://local.shopware';
        $originalQuery = 'FINDOLC';

        $originalQueryMock = $this->getMockBuilder(OriginalQuery::class)->disableOriginalConstructor()->getMock();
        $originalQueryMock->expects($this->once())->method('getValue')->willReturn($originalQuery);

        $queryStringMock = $this->getMockBuilder(QueryString::class)->disableOriginalConstructor()->getMock();
        $queryStringMock->expects($this->once())->method('getType')->willReturn('improved');

        $expectedLink = sprintf(
            '%s?search=%s&forceOriginalQuery=1',
            $controllerPath,
            $originalQuery
        );

        /** @var Query|MockObject $mockQuery */
        $mockQuery = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $mockQuery->expects($this->once())->method('getDidYouMeanQuery')->willReturn(null);
        $mockQuery->expects($this->once())->method('getQueryString')->willReturn($queryStringMock);

        $smartDidYouMean = new SmartDidYouMean($mockQuery, $controllerPath);
        $this->assertSame($expectedLink, $smartDidYouMean->getLink());
    }

    public function typeProvider(): array
    {
        return [
            'Type is "corrected"' => ['corrected'],
            'Type is "blubbergurken"' => ['blubbergurken'],
        ];
    }

    /**
     * @dataProvider typeProvider
     */
    public function testWhenLinkIsNull(string $type): void
    {
        $controllerPath = 'https://local.shopware';
        $originalQuery = 'FINDOLC';

        $originalQueryMock = $this->getMockBuilder(OriginalQuery::class)->disableOriginalConstructor()->getMock();
        $originalQueryMock->expects($this->once())->method('getValue')->willReturn($originalQuery);

        $queryStringMock = $this->getMockBuilder(QueryString::class)->disableOriginalConstructor()->getMock();
        $queryStringMock->expects($this->once())->method('getType')->willReturn($type);

        /** @var Query|MockObject $mockQuery */
        $mockQuery = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $mockQuery->expects($this->once())->method('getDidYouMeanQuery')->willReturn(null);
        $mockQuery->expects($this->once())->method('getQueryString')->willReturn($queryStringMock);

        $smartDidYouMean = new SmartDidYouMean($mockQuery, $controllerPath);
        $this->assertNull($smartDidYouMean->getLink());
    }
}
