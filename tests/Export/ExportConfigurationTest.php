<?php

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

class ExportConfigurationTest extends TestCase
{
    public function testGetInstanceReturnsConfigWithGivenArguments(): void
    {
        $expectedShopkey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';
        $expectedStart = 0;
        $expectedCount = 100;

        $request = new Request([
            'shopkey' => $expectedShopkey,
            'start' => $expectedStart,
            'count' => $expectedCount
        ]);

        $config = ExportConfiguration::getInstance($request);

        $this->assertSame($expectedShopkey, $config->getShopkey());
        $this->assertSame($expectedStart, $config->getStart());
        $this->assertSame($expectedCount, $config->getCount());
    }

    public function testDefaultsAreSet(): void
    {
        $expectedDefaultStart = 0;
        $expectedDefaultCount = 20;

        $request = new Request([
            'shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
        ]);

        $config = ExportConfiguration::getInstance($request);

        $this->assertSame($expectedDefaultStart, $config->getStart());
        $this->assertSame($expectedDefaultCount, $config->getCount());
    }

    public function testProductIdIsSetWhenGiven(): void
    {
        $expectedProductId = '03cca9ceac4047e4b331b6827e245594';

        $request = new Request([
            'shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
            'productId' => $expectedProductId
        ]);

        $config = ExportConfiguration::getInstance($request);

        $this->assertSame($expectedProductId, $config->getProductId());
    }

    public function invalidConfigurationProvider(): array
    {
        return [
            'No parameters given' => [
                'queryParams' => []
            ],
            'Shopkey does not match the schema' => [
                'queryParams' => [
                    'shopkey' => 'hehe i am a bad shopkey'
                ]
            ],
            'Shopkey matches the schema but count is negative' => [
                'queryParams' => [
                    'shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                    'count' => -1,
                ]
            ],
            'Shopkey matches the schema but start is negative' => [
                'queryParams' => [
                    'shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                    'start' => -1,
                ]
            ],
            'Shopkey matches the schema but count is zero' => [
                'queryParams' => [
                    'shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                    'count' => 0,
                ]
            ],
            'All params are invalid' => [
                'queryParams' => [
                    'shopkey' => 'i am invalid',
                    'count' => -55,
                    'start' => -134,
                ]
            ],
        ];
    }

    /**
     * @dataProvider invalidConfigurationProvider
     */
    public function testInvalidConfigurationIsDetected(
        array $queryParams
    ): void {
        $request = new Request($queryParams);
        $config = ExportConfiguration::getInstance($request);

        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($config);

        $this->assertGreaterThan(0, $violations->count());
    }
}
