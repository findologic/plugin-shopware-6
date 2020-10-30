<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\WithTestClient;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Development\Kernel;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;

class ExportControllerTest extends TestCase
{
    use WithTestClient;
    use SalesChannelHelper;
    use ProductHelper;

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    /** @var SalesChannelContext */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function testExportsSingleProduct(): void
    {
        $product = $this->createVisibleTestProduct();
        $this->enableFindologicInPluginConfiguration();

        $response = $this->sendRequest();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/xml; charset=UTF-8', $response->headers->get('content-type'));
        $parsedResponse = new SimpleXMLElement($response->getContent());

        $this->assertSame($product->getId(), $parsedResponse->items->item->attributes()->id->__toString());
    }

    public function testExportOnlySingleProductWhenProductIdIsGiven(): void
    {
        // Create two products.
        $product = $this->createVisibleTestProduct();
        $this->createVisibleTestProduct(['productNumber' => 'FINDO002']);

        $this->enableFindologicInPluginConfiguration();

        $response = $this->sendRequest(['productId' => $product->getId()]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/xml; charset=UTF-8', $response->headers->get('content-type'));
        $parsedResponse = new SimpleXMLElement($response->getContent());

        $this->assertSame(1, (int)$parsedResponse->items->attributes()->count);
        $this->assertSame(2, (int)$parsedResponse->items->attributes()->total);
        $this->assertSame($product->getId(), $parsedResponse->items->item->attributes()->id->__toString());
    }

    public function testExportWithUnknownShopkey(): void
    {
        $unknownShopkey = '12341234123412341234123412341234';
        $response = $this->sendRequest(['shopkey' => $unknownShopkey]);

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
        return [
            'Count of zero' => [
                'params' => ['count' => 0],
                'errorMessages' => ['count: This value should be greater than 0.'],
            ],
            'Start is negative and count is zero' => [
                'params' => [
                    'start' => -5,
                    'count' => 0
                ],
                'errorMessages' => [
                    'start: This value should be greater than or equal to 0.',
                    'count: This value should be greater than 0.'
                ],
            ],
            'Sales channel is not searched when start is negative, count is negative and shopkey is invalid' => [
                'params' => [
                    'start' => -5,
                    'count' => 0,
                    'shopkey' => 'I do not follow the shopkey schema'
                ],
                'errorMessages' => [
                    'shopkey: This value is not valid.',
                    'start: This value should be greater than or equal to 0.',
                    'count: This value should be greater than 0.',
                ],
            ],
            'Sales channel is searched when shopkey is valid but is not assigned to a sales channel' => [
                'params' => [
                    'shopkey' => 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'
                ],
                'errorMessages' => [
                    'Shopkey FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF is not assigned to any sales channel.',
                ],
            ]
        ];
    }

    /**
     * @dataProvider wrongArgumentsProvider
     */
    public function testExportWithWrongArguments(array $params, array $errorMessages): void
    {
        if (!isset($params['shopkey'])) {
            $this->enableFindologicInPluginConfiguration();
        }

        $response = $this->sendRequest($params);

        $this->assertSame(422, $response->getStatusCode());
        $parsedResponse = json_decode($response->getContent(), true);

        $this->assertSame(
            $errorMessages,
            $parsedResponse['general']
        );
    }

    public function testExportHeadersAreSet(): void
    {
        $this->createVisibleTestProduct();
        $this->enableFindologicInPluginConfiguration();

        $response = $this->sendRequest();

        $expectedShopwareVersion = sprintf('Shopware/%s', Kernel::SHOPWARE_FALLBACK_VERSION);
        $expectedPluginVersion = sprintf('Plugin-Shopware-6/%s', $this->parsePluginVersion());
        $expectedExtensionPluginVersion = 'none';

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($response->headers->get('x-findologic-platform'), $expectedShopwareVersion);
        $this->assertSame($response->headers->get('x-findologic-plugin'), $expectedPluginVersion);
        $this->assertSame($response->headers->get('x-findologic-extension-plugin'), $expectedExtensionPluginVersion);
    }

    protected function enableFindologicInPluginConfiguration(): void
    {
        $configService = $this->getContainer()->get(SystemConfigService::class);
        $configService->set(
            'FinSearch.config.active',
            true,
            $this->salesChannelContext->getSalesChannel()->getId()
        );
        $configService->set(
            'FinSearch.config.shopkey',
            self::VALID_SHOPKEY,
            $this->salesChannelContext->getSalesChannel()->getId()
        );
    }

    protected function sendRequest(array $overrides = []): Response
    {
        $defaults = [
            'shopkey' => self::VALID_SHOPKEY
        ];

        $params = array_merge($defaults, $overrides);
        $client = $this->getTestClient($this->salesChannelContext);

        $client->request('GET', '/findologic?' . http_build_query($params));

        return $client->getResponse();
    }

    protected function parsePluginVersion(): string
    {
        $composerJsonContents = file_get_contents(__DIR__ . '/../../composer.json');
        $parsed = json_decode($composerJsonContents, true);

        return ltrim($parsed['version'], 'v');
    }
}
