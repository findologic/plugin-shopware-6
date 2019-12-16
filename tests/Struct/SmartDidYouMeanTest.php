<?php

declare(strict_types=1);

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
        $alternativeQuery = 'FINDOLOGIC';
        $expectedLink = sprintf(
            '%s?search=%s&forceOriginalQuery=1',
            $controllerPath,
            $alternativeQuery
        );

        /** @var Query|MockObject $mockQuery */
        $mockQuery = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $mockQuery->expects($this->once())->method('getDidYouMeanQuery')->willReturn('FINDOLOGIC');
        $mockQuery->expects($this->once())->method('getAlternativeQuery')->willReturn($alternativeQuery);
        $smartDidYouMean = new SmartDidYouMean($mockQuery, $controllerPath);
        $parameters = $smartDidYouMean->getVars();
        $this->assertNotEmpty($parameters);
        $this->assertSame($expectedLink, $parameters['link']);
        $this->assertSame($alternativeQuery, $parameters['alternativeQuery']);
        $this->assertEmpty($parameters['originalQuery']);
        $this->assertSame($controllerPath, $parameters['controllerPath']);
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
        $mockQuery->expects($this->exactly(2))->method('getOriginalQuery')->willReturn($originalQueryMock);

        $smartDidYouMean = new SmartDidYouMean($mockQuery, $controllerPath);
        $parameters = $smartDidYouMean->getVars();
        $this->assertNotEmpty($parameters);
        $this->assertSame($expectedLink, $parameters['link']);
        $this->assertSame($originalQuery, $parameters['originalQuery']);
        $this->assertNull($parameters['alternativeQuery']);
        $this->assertSame($controllerPath, $parameters['controllerPath']);
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
    public function testWhenTypeIsCorrectedOrAnythingElse(string $type): void
    {
        $controllerPath = 'https://local.shopware';
        $queryStringMock = $this->getMockBuilder(QueryString::class)->disableOriginalConstructor()->getMock();
        $queryStringMock->expects($this->once())->method('getType')->willReturn($type);

        /** @var Query|MockObject $mockQuery */
        $mockQuery = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $mockQuery->expects($this->once())->method('getDidYouMeanQuery')->willReturn(null);
        $mockQuery->expects($this->once())->method('getQueryString')->willReturn($queryStringMock);

        $smartDidYouMean = new SmartDidYouMean($mockQuery, $controllerPath);
        $parameters = $smartDidYouMean->getVars();
        $this->assertNotEmpty($parameters);
        $this->assertNull($parameters['link']);
        $this->assertNull($parameters['alternativeQuery']);
        $this->assertEmpty($parameters['originalQuery']);
        $this->assertSame($controllerPath, $parameters['controllerPath']);
    }
}
