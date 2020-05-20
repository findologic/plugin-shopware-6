<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
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
                'expectedLink' => 'https://localhost/findologic?search=alternativeQuery&forceOriginalQuery=1'
            ],
            'Query type is "improved"' => [
                'didYouMeanQuery' => null,
                'alternativeQuery' => 'alternativeQuery',
                'originalQuery' => 'originalQuery',
                'type' => 'improved',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => 'https://localhost/findologic?search=originalQuery&forceOriginalQuery=1'
            ],
            'Query type is "corrected"' => [
                'didYouMeanQuery' => null,
                'alternativeQuery' => 'alternativeQuery',
                'originalQuery' => 'originalQuery',
                'type' => 'corrected',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => null
            ],
            'Query type is "blubbergurken"' => [
                'didYouMeanQuery' => null,
                'alternativeQuery' => 'alternativeQuery',
                'originalQuery' => 'originalQuery',
                'type' => 'blubbergurken',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => null
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
        ?string $expectedLink
    ): void {
        $smartDidYouMean = new SmartDidYouMean(
            $originalQuery,
            $alternativeQuery,
            $didYouMeanQuery,
            $type,
            $controllerPath
        );
        $parameters = $smartDidYouMean->getVars();

        static::assertNotEmpty($parameters);
        static::assertSame($expectedLink, $parameters['link']);
        static::assertSame($alternativeQuery, $parameters['alternativeQuery']);
        static::assertSame($originalQuery, $parameters['originalQuery']);
    }
}
