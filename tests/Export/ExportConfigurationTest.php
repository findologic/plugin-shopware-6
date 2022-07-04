<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Validators\DebugExportConfiguration;
use FINDOLOGIC\FinSearch\Validators\ExportConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;
use Throwable;

class ExportConfigurationTest extends TestCase
{
    public function testGetInstanceReturnsConfigWithGivenArguments(): void
    {
        $expectedShopkey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';
        $expectedStart = 0;
        $expectedCount = 100;

        $request = $this->createRequest([
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

        $request = $this->createRequest([
            'shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
        ]);

        $config = ExportConfiguration::getInstance($request);

        $this->assertSame($expectedDefaultStart, $config->getStart());
        $this->assertSame($expectedDefaultCount, $config->getCount());
    }

    public function testProductIdIsSetWhenGiven(): void
    {
        $expectedProductId = '03cca9ceac4047e4b331b6827e245594';

        $request = $this->createRequest([
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
        $request = $this->createRequest($queryParams);
        $config = ExportConfiguration::getInstance($request);

        try {
            // Symfony >= 5
            $validator = Validation::createValidatorBuilder()
                ->enableAnnotationMapping(true)
                ->addDefaultDoctrineAnnotationReader()
                ->getValidator();
        } catch (Throwable $e) {
            // Symfony 4
            $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        }

        $violations = $validator->validate($config);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function pathProvider(): array
    {
        return [
            'Export path' => [
                'path' => 'findologic',
                'expectedClass' => ExportConfiguration::class
            ],
            'Export debug path' => [
                'path' => 'findologic/debug',
                'expectedClass' => DebugExportConfiguration::class
            ],
        ];
    }

    /**
     * @dataProvider pathProvider
     */
    public function testGetInstanceReturnsCorrectConfiguration(string $path, $expectedClass): void
    {
        $request = $this->createRequest([], $path);

        $config = ExportConfiguration::getInstance($request);

        $this->assertEquals($expectedClass, get_class($config));
    }

    private function createRequest(?array $query = [], ?string $path = 'findologic'): Request
    {
        return new Request($query, [], [], [], [], ['REQUEST_URI' => 'https://example.com/' . $path]);
    }
}
