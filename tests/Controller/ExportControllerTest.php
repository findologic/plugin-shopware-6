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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Development\Kernel;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;

class ExportControllerTest extends TestCase
{
    use WithTestClient;
    use SalesChannelHelper;
    use ProductHelper;
    use PluginConfigHelper;
    use ExportHelper;

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    /** @var SalesChannelContext */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function testExportOfSingleProduct(): void
    {
        $product = $this->createVisibleTestProduct();
        $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $this->salesChannelContext);

        $response = $this->sendExportRequest();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/xml; charset=UTF-8', $response->headers->get('content-type'));
        $parsedResponse = new SimpleXMLElement($response->getContent());

        $this->assertSame($product->getId(), $parsedResponse->items->item->attributes()->id->__toString());
    }

    public function testSingleProductIsExportedWhenProductIdIsGiven(): void
    {
        // Create two products.
        $product = $this->createVisibleTestProduct();
        $this->createVisibleTestProduct(['productNumber' => 'FINDO002']);

        $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $this->salesChannelContext);

        $response = $this->sendExportRequest(['productId' => $product->getId()]);

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
                    'shopkey: Invalid key provided.',
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

    public function testExportHeadersAreSet(): void
    {
        $this->createVisibleTestProduct();
        $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $this->salesChannelContext);

        $response = $this->sendExportRequest();

        $expectedShopwareVersion = sprintf('Shopware/%s', Kernel::SHOPWARE_FALLBACK_VERSION);
        $expectedPluginVersion = sprintf('Plugin-Shopware-6/%s', $this->parsePluginVersion());
        $expectedExtensionPluginVersion = 'none';

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($response->headers->get('x-findologic-platform'), $expectedShopwareVersion);
        $this->assertSame($response->headers->get('x-findologic-plugin'), $expectedPluginVersion);
        $this->assertSame($response->headers->get('x-findologic-extension-plugin'), $expectedExtensionPluginVersion);
    }

    protected function sendExportRequest(array $overrides = []): Response
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

    public function testCorrectTranslatedProductIsExported(): void
    {
        $salesChannelService = $this->getContainer()->get(SalesChannelService::class);
        $product = $this->createVisibleTestProduct();

        $anotherShopkey = '1BCDABCDABCDABCDABCDABCDABCDABC1';
        $this->enableFindologicPlugin($this->getContainer(), $anotherShopkey, $this->salesChannelContext);
        $this->salesChannelContext = $salesChannelService->getSalesChannelContext(
            $this->salesChannelContext,
            $anotherShopkey
        );
        $response = $this->sendExportRequest(['productId' => $product->getId(), 'shopkey' => $anotherShopkey]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/xml; charset=UTF-8', $response->headers->get('content-type'));

        $parsedResponse = new SimpleXMLElement($response->getContent());

        $this->assertSame(1, (int)$parsedResponse->items->attributes()->count);
        $this->assertSame('FINDOLOGIC Product', $parsedResponse->items->item->names->name->__toString());

        // Reset it here otherwise it will fetch the same service instance from the container
        // @see \FINDOLOGIC\FinSearch\Export\ProductService::getInstance
        $this->getContainer()->set('fin_search.product_service', null);

        $salesChannelContext = $this->buildSalesChannelContext(
            Defaults::SALES_CHANNEL,
            'http://test.abc',
            null,
            $this->getDeDeLanguageId()
        );
        $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $salesChannelContext);
        $this->salesChannelContext = $salesChannelService->getSalesChannelContext(
            $salesChannelContext,
            self::VALID_SHOPKEY
        );
        $response = $this->sendExportRequest(['productId' => $product->getId()]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/xml; charset=UTF-8', $response->headers->get('content-type'));

        $parsedResponse = new SimpleXMLElement($response->getContent());

        $this->assertSame(1, (int)$parsedResponse->items->attributes()->count);
        $this->assertSame('FINDOLOGIC Product DE', $parsedResponse->items->item->names->name->__toString());
    }
}
