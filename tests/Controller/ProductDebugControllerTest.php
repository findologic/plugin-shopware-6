<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ExportHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\WithTestClient;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Development\Kernel;
use SimpleXMLElement;
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

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    /** @var SalesChannelContext */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function testExportWithUnknownShopkey(): void
    {
        $unknownShopkey = '12341234123412341234123412341234';
        $response = $this->sendExportRequest(['shopkey' => $unknownShopkey]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('content-type'));
        $parsedResponse = json_decode($response->getContent(), true);

        $this->assertCount(1, $parsedResponse['general']);
        $this->assertSame(
            sprintf('Shopkey %s is not assigned to any sales channel.', $unknownShopkey),
            $parsedResponse['general'][0]
        );
    }

    public function wrongArgumentsProvider(): array
    {
        $productId = Uuid::randomHex();

        return [
            'Invalid shopkey applied' => [
                'params' => [
                    'shopkey' => '1234'
                ],
                'errorMessages' => [
                    'shopkey: Invalid key provided.',
                ],
            ],
            'Invalid productId applied' => [
                'params' => [
                    'productId' => 'notAnUuid'
                ],
                'errorMessages' => [
                    'productId: This is not a valid UUID.',
                ],
            ],
            'Sales channel is searched when shopkey is valid but is not assigned to a sales channel' => [
                'params' => [
                    'shopkey' => 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'
                ],
                'errorMessages' => [
                    'Shopkey FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF is not assigned to any sales channel.',
                ],
            ],
            'Unknown productId applied' => [
                'params' => [
                    'productId' => $productId,
                ],
                'errorMessages' => [
                    'Product could not be found or is not available for search.',
                    sprintf(
                        'Product or variant with ID %s does not exist.',
                        $productId
                    )
                ],
            ],
        ];
    }

    /**
     * @dataProvider wrongArgumentsProvider
     */
    public function testExportWithWrongArguments(array $params, array $errorMessages): void
    {
        if (!isset($params['shopkey'])) {
            $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $this->salesChannelContext);
        }

        $response = $this->sendExportRequest($params);

        $this->assertSame(422, $response->getStatusCode());
        $parsedResponse = json_decode($response->getContent(), true);

        $this->assertSame(
            $errorMessages,
            $parsedResponse['general']
        );
    }

    public function testExportWithCorrectArguments(): void
    {
        $product = $this->createTestProduct();
        $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $this->salesChannelContext);

        $response = $this->sendExportRequest(['productId' => $product->getId()]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    protected function sendExportRequest(array $overrides = []): Response
    {
        $defaults = [
            'shopkey' => self::VALID_SHOPKEY,
            'productId' => Uuid::randomHex()
        ];

        $params = array_merge($defaults, $overrides);
        $client = $this->getTestClient($this->salesChannelContext);
        $client->request('GET', '/findologic/debug?' . http_build_query($params));

        return $client->getResponse();
    }
}
