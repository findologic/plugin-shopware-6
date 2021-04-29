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
        $this->createVisibleTestProduct(['id' => '951cf29f69086aab521e859faa152d2b', 'productNumber' => 'FINDO002']);

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
        $this->assertSame($expectedShopwareVersion, $response->headers->get('x-findologic-platform'));
        $this->assertSame($expectedPluginVersion, $response->headers->get('x-findologic-plugin'));
        $this->assertSame($expectedExtensionPluginVersion, $response->headers->get('x-findologic-extension-plugin'));
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

        // For release candidates, Shopware will internally format the version different, from how
        // it is set in the composer.json.
        return str_replace('rc.', 'RC', ltrim($parsed['version'], 'v'));
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

    /**
     * Test ensures that the Shopware Router generates the product URLs based on the current language.
     * This special case applies if a Sales Channel has multiple languages and the export is called from
     * within a different Sales Channel. E.g.
     *
     * Sales Channel "Germany" can be accessed with URL https://some-shop.de, therefore the configured export
     * URL is set to it. Now there is a second domain for "United Kingdom", which can be accessed with the
     * URL https://some-shop.co.uk. This test ensures that the products will use the UK domain, when calling
     * the URL of the German Sales Channel with the shopkey of the United Kingdom Sales Channel:
     * https://some-shop.de/findologic?shopkey=from-united-kingdom => https://shome-shop.co.uk/detail/...
     */
    public function testProductsWithoutSeoUrlsWillExportTheUrlBasedOnTheConfiguredLanguage(): void
    {
        $langRepo = $this->getContainer()->get('language.repository');
        $languages = $langRepo->search(
            (new Criteria())->addSorting(new FieldSorting('name')),
            Context::createDefaultContext()
        );
        $languageId = Defaults::LANGUAGE_SYSTEM;

        $currencyRepo = $this->getContainer()->get('currency.repository');
        $currencies = $currencyRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', 'EUR')),
            Context::createDefaultContext()
        );

        $salesChannelContext = $this->createSalesChannelContext($currencies, $languages, $languageId);
        $salesChannelContext->getSalesChannel()->setLanguageId($languageId);

        $this->getContainer()->get('sales_channel.repository')->update([
            [
                'id' => $salesChannelContext->getSalesChannel()->getId(),
                'domains' => [
                    [
                        'currencyId' => $currencies->first()->getId(),
                        'snippetSetId' =>
                            $salesChannelContext->getSalesChannel()->getDomains()->first()->getSnippetSetId(),
                        'url' => 'http://cool-url.com/german'
                    ]
                ]
            ],
        ], Context::createDefaultContext());

        $this->enableFindologicPlugin(
            $this->getContainer(),
            self::VALID_SHOPKEY,
            $salesChannelContext
        );

        $product = $this->createVisibleTestProduct([
            'visibilities' => [
                [
                    'id' => Uuid::randomHex(),
                    'salesChannelId' => $salesChannelContext->getSalesChannel()->getId(),
                    'visibility' => 20
                ]
            ],
            'seoUrls' => []
        ]);

        // Explicitly remove all SEO URLs, since they were automatically created when using DAL for product creation.
        Kernel::getConnection()->executeUpdate('DELETE FROM seo_url');

        $response = $this->sendExportRequest();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/xml; charset=UTF-8', $response->headers->get('content-type'));
        $parsedResponse = new SimpleXMLElement($response->getContent());

        $this->assertSame(1, (int)$parsedResponse->items->attributes()->count);
        $this->assertSame($product->getId(), $parsedResponse->items->item->attributes()->id->__toString());
        $this->assertSame(
            'http://localhost/german/detail/' . $product->getId(),
            $parsedResponse->items->item->urls->url->__toString()
        );
    }

    /**
     * Unlike SalesChannelHelper::buildSalesChannelContext, which by default modifies the default sales channel, this
     * method creates an entirely new Sales Channel and returns an appropriate SalesChannelContext.
     *
     * @see SalesChannelHelper::buildSalesChannelContext
     */
    private function createSalesChannelContext(
        EntitySearchResult $currencies,
        EntitySearchResult $languages,
        string $languageId
    ): SalesChannelContext {
        $deliveryTimeRepo = $this->getContainer()->get('delivery_time.repository');
        $deliveryTimes = $deliveryTimeRepo->search(new Criteria(), Context::createDefaultContext());

        $overrides = [
            'languageId' => $languageId,
            'languages' => [
                [
                    'id' => $languages->first()->getId(),
                ],
                [
                    'id' => $languageId
                ]
            ],
            'customerGroup' => [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Nice Customer Group!'
                    ]
                ]
            ],
            'currencyId' => $currencies->first()->getId(),
            'payment' => [],
            'shippingMethod' => [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Findologic Speed Delivery'
                    ]
                ],
                'availabilityRule' => [
                    'name' => 'Verfügbarkeit',
                    'priority' => 100
                ],
                'deliveryTimeId' => $deliveryTimes->first()->getId()
            ],
            'country' => [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Austria'
                    ]
                ],
            ],
            'navigationCategory' => [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'MainCategoryForCoolSalesChannel'
                    ]
                ],
            ],
            'paymentMethod' => [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'FindoPayments'
                    ]
                ],
            ],
            'accessKey' => 'verySecure1234',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'Cool URL Sales Channel'
                ]
            ],

        ];

        return $this->buildSalesChannelContext(
            Uuid::randomHex(),
            'http://cool-url.com',
            null,
            $languages->first()->getId(),
            $overrides
        );
    }
}
