<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
use PHPUnit\Framework\TestCase;

class SmartDidYouMeanTest extends TestCase
{
    public static function queryParameterProvider(): array
    {
        return [
            'Query type is "did-you-mean"' => [
                'originalQuery' => '',
                'correctedQuery' => null,
                'didYouMeanQuery' => 'didYouMeanQuery',
                'improvedQueryQuery' => null,
                'type' => 'did-you-mean',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => 'https://localhost/findologic?search=didYouMeanQuery&forceOriginalQuery=1'
            ],
            'Query type is "improved"' => [
                'originalQuery' => '',
                'correctedQuery' => null,
                'didYouMeanQuery' => null,
                'improvedQueryQuery' => 'improved',
                'type' => 'improved',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => 'https://localhost/findologic?search=improved&forceOriginalQuery=1'
            ],
            'Query type is "corrected"' => [
                'originalQuery' => '',
                'correctedQuery' => 'corrected',
                'didYouMeanQuery' => null,
                'improvedQueryQuery' => null,
                'type' => 'corrected',
                'controllerPath' => 'https://localhost/findologic',
                'expectedLink' => null
            ],
            'Query type is "blubbergurken"' => [
                'originalQuery' => '',
                'correctedQuery' => null,
                'didYouMeanQuery' => null,
                'improvedQueryQuery' => null,
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
        ?string $originalQuery,
        ?string $correctedQuery,
        ?string $didYouMeanQuery,
        ?string $improvedQueryQuery,
        string $type,
        string $controllerPath,
        ?string $expectedLink
    ): void {
        $smartDidYouMean = new SmartDidYouMean(
            $originalQuery,
            $originalQuery,
            $correctedQuery,
            $didYouMeanQuery,
            $improvedQueryQuery,
            $controllerPath
        );
        $parameters = $smartDidYouMean->getVars();

        $this->assertNotEmpty($parameters);
        $this->assertSame($expectedLink, $parameters['link']);
        $this->assertSame($originalQuery, $parameters['originalQuery']);
        $this->assertSame($originalQuery, $parameters['effectiveQuery']);
        $this->assertSame($correctedQuery ?? '', $parameters['correctedQuery']);
        $this->assertSame($didYouMeanQuery ?? '', $parameters['didYouMeanQuery']);
        $this->assertSame($improvedQueryQuery ?? '', $parameters['improvedQuery']);
    }
}
