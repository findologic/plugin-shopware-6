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
    public function queryParameterProvider(): array
    {
        return [
            'Query type is "did-you-mean"' => [
                'didYouMeanQuery' => 'didYouMeanQuery',
                'alternativeQuery' => 'alternativeQuery',
                'originalQuery' => '',
                'type' => '',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => 'https://localhost/findologic?search=alternativeQuery&forceOriginalQuery=1',
                'invokeCount' => 0
            ],
            'Query type is "improved"' => [
                'didYouMeanQuery' => null,
                'alternativeQuery' => 'alternativeQuery',
                'originalQuery' => 'originalQuery',
                'type' => 'improved',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => 'https://localhost/findologic?search=originalQuery&forceOriginalQuery=1',
                'invokeCount' => 1
            ],
            'Query type is "corrected"' => [
                'didYouMeanQuery' => null,
                'alternativeQuery' => 'alternativeQuery',
                'originalQuery' => 'originalQuery',
                'type' => 'corrected',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => null,
                'invokeCount' => 1
            ],
            'Query type is "blubbergurken"' => [
                'didYouMeanQuery' => null,
                'alternativeQuery' => 'alternativeQuery',
                'originalQuery' => 'originalQuery',
                'type' => 'blubbergurken',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => null,
                'invokeCount' => 1
            ],
        ];
    }

    /**
     * @dataProvider queryParameterProvider
     */
    public function testParametersBasedOnQuery(
        ?string $didYouMeanQuery,
        string $alternativeQuery,
        string $originalQuery,
        string $type,
        string $controllerPath,
        ?string $expectedLink,
        int $invokeCount
    ): void {
        $originalQueryMock = $this->getMockBuilder(OriginalQuery::class)->disableOriginalConstructor()->getMock();
        $originalQueryMock->expects($this->once())->method('getValue')->willReturn($originalQuery);

        $queryStringMock = $this->getMockBuilder(QueryString::class)->disableOriginalConstructor()->getMock();
        $queryStringMock->expects($this->exactly($invokeCount))->method('getType')->willReturn($type);

        /** @var Query|MockObject $mockQuery */
        $mockQuery = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $mockQuery->expects($this->once())->method('getDidYouMeanQuery')->willReturn($didYouMeanQuery);
        $mockQuery->expects($this->exactly($invokeCount))->method('getQueryString')->willReturn($queryStringMock);
        $mockQuery->expects($this->exactly(2))->method('getOriginalQuery')->willReturn($originalQueryMock);
        $mockQuery->expects($this->once())->method('getAlternativeQuery')->willReturn($alternativeQuery);

        $smartDidYouMean = new SmartDidYouMean($mockQuery, $controllerPath);
        $parameters = $smartDidYouMean->getVars();

        $this->assertNotEmpty($parameters);
        $this->assertSame($expectedLink, $parameters['link']);
        $this->assertSame($alternativeQuery, $parameters['alternativeQuery']);
        $this->assertSame($originalQuery, $parameters['originalQuery']);
    }
}
