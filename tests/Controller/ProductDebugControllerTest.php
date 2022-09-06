<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ExportHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\WithTestClient;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for the ExportController. All tests are run in separate processes to not interfere with each other.
 *
 * @runTestsInSeparateProcesses
 */
class ProductDebugControllerTest extends TestCase
{
    use WithTestClient;
    use SalesChannelHelper;
    use ProductHelper;
    use PluginConfigHelper;
    use ExportHelper;

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function wrongArgumentsProvider(): array
    {
        $productId = Uuid::randomHex();

        return [
            'Invalid shopkey' => [
                'params' => [
                    'shopkey' => 'I do not follow the shopkey schema',
                    'productId' => $productId
                ],
                'expectedStatusCode' => 422,
                'errorMessages' => ['shopkey: Invalid key provided.'],
            ],
            'Unknown shopkey' => [
                'params' => [
                    'shopkey' => 'ABCDEABCDEABCDEABCDEABCDEABCDEAB',
                    'productId' => $productId
                ],
                'expectedStatusCode' => 422,
                'errorMessages' => [
                    'Shopkey ABCDEABCDEABCDEABCDEABCDEABCDEAB is not assigned to any sales channel.',
                ],
            ],
            'Invalid product id' => [
                'params' => [
                    'shopkey' => self::VALID_SHOPKEY,
                    'productId' => 'not a UUID'
                ],
                'expectedStatusCode' => 422,
                'errorMessages' => [
                    'productId: This is not a valid UUID.',
                ],
            ],
            'Unkown product id' => [
                'params' => [
                    'shopkey' => self::VALID_SHOPKEY,
                    'productId' => $productId
                ],
                'expectedStatusCode' => 422,
                'errorMessages' => [
                    'Product could not be found or is not available for search.',
                    sprintf('Product or variant with ID %s does not exist.', $productId)
                ],
            ],
            'Known shopkey and product id' => [
                'params' => [
                    'shopkey' => self::VALID_SHOPKEY,
                    // productId is set in test
                ],
                'expectedStatusCode' => 200,
                'errorMessages' => [],
            ]
        ];
    }

    /**
     * @dataProvider wrongArgumentsProvider
     */
    public function testExportWithWrongArguments(array $params, int $expectedStatusCode, array $errorMessages): void
    {
        if (!isset($params['productId'])) {
            $product = $this->createVisibleTestProduct();
            $params['productId'] = $product->getId();
        }

        $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $this->salesChannelContext);

        $response = $this->sendExportRequest($params);

        $this->assertSame($expectedStatusCode, $response->getStatusCode());
        $parsedResponse = json_decode($response->getContent(), true);

        if (count($errorMessages)) {
            $this->assertSame(
                $errorMessages,
                $parsedResponse['general']
            );
        }
    }

    protected function sendExportRequest(array $overrides = []): Response
    {
        $defaults = [
            'shopkey' => self::VALID_SHOPKEY
        ];

        $params = array_merge($defaults, $overrides);
        $client = $this->getTestClient($this->salesChannelContext);
        $client->request('GET', '/findologic/debug?' . http_build_query($params));

        return $client->getResponse();
    }
}
