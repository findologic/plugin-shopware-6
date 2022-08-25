<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Debug;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Debug\ProductDebugSearcher;
use FINDOLOGIC\FinSearch\Export\Debug\ProductDebugService;
use FINDOLOGIC\FinSearch\Export\Errors\ExportErrors;
use FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductServiceTest extends TestCase
{
    use ProductHelper;
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ConfigHelper;

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    /** @var ProductDebugService */
    private $defaultDebugService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();

        $mockedConfig = $this->getFindologicConfig(['mainVariant' => 'default']);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $productCriteriaBuilder = new ProductCriteriaBuilder(
            $this->salesChannelContext,
            $this->getContainer()->get(SystemConfigService::class),
            $this->getContainer()->get(Config::class)
        );
        $defaultProductDebugSearcher = new ProductDebugSearcher(
            $this->salesChannelContext,
            $this->getContainer()->get('product.repository'),
            $productCriteriaBuilder,
            $mockedConfig
        );

        $this->defaultProductService = new ProductDebugService(
            $this->salesChannelContext,
            $defaultProductDebugSearcher,
            $productCriteriaBuilder
        );
    }

    public function testProductIdIsSet(): void
    {
        $productId = Uuid::randomHex();

        $data = $this->getDebugInformation($productId);

        $this->assertSame(
            $data['export']['productId'],
            $productId
        );
    }

    public function testExportedMainProductIdIsSet(): void
    {
        $productId = Uuid::randomHex();
        $mainProductId = Uuid::randomHex();

        $data = $this->getDebugInformation($productId, $mainProductId);

        $this->assertSame(
            $data['export']['productId'],
            $productId
        );
        $this->assertSame(
            $data['export']['exportedMainProductId'],
            $mainProductId
        );
    }

    public function testIsExportedFalseWithoutXmlItem(): void
    {
        $productId = Uuid::randomHex();
        $mainProductId = Uuid::randomHex();

        $data = $this->getDebugInformation($productId, $mainProductId, null);

        $this->assertFalse($data['export']['isExported']);
        $this->assertContains(
            'Product is not visible for search',
            $data['export']['reasons']
        );
    }

    public function testWithDifferentProduct(): void
    {
        $productId = Uuid::randomHex();
        $mainProductId = Uuid::randomHex();

        $data = $this->getDebugInformation($productId, $mainProductId);

        $this->assertFalse($data['export']['isExported']);
        $this->assertContains(
            'Product is not visible for search',
            $data['export']['reasons']
        );
        $this->assertContains(
            'Product is not the exported variant.',
            $data['export']['reasons']
        );

        $this->assertStringContainsString($mainProductId, $data['debugLinks']['exportUrl']);
        $this->assertStringContainsString($mainProductId, $data['debugLinks']['debugUrl']);

        $this->assertFalse($data['data']['isExportedMainVariant']);
        $this->assertSame($productId, $data['data']['product']['id']);
    }

    public function testWithCorrectProductGiven(): void
    {
        $productId = Uuid::randomHex();

        $data = $this->getDebugInformation($productId, $productId);

        $this->assertTrue($data['export']['isExported']);
        $this->assertEmpty($data['export']['reasons']);

        $this->assertStringContainsString($productId, $data['debugLinks']['exportUrl']);
        $this->assertStringContainsString($productId, $data['debugLinks']['debugUrl']);

        $this->assertTrue($data['data']['isExportedMainVariant']);
        $this->assertSame($productId, $data['data']['product']['id']);
    }

    public function testSiblingsAreSet(): void
    {
        $product = $this->createProductWithMultipleVariants();
        $firstChild = $product->getChildren()->first();

        $xmlItem = new XMLItem($firstChild->getId());

        $data = $this->defaultProductService->getDebugInformation(
            $firstChild->getId(),
            self::VALID_SHOPKEY,
            $xmlItem,
            $firstChild,
            new ExportErrors()
        )->getContent();
        $json = json_decode($data, true);

        $this->assertCount(3, $json['data']['siblings']);
    }

    public function testAssociationsSet(): void
    {
        $data = $this->getDebugInformation();

        $this->assertNotEmpty($data['data']['associations']);
    }

    private function getDebugInformation(
        ?string $productId = null,
        ?string $mainProductId = null,
        ?bool $withXmlItem = true
    ): array {
        $product = $this->createTestProduct([
            'id' => $productId ?? Uuid::randomHex(),
            'productNumber' => 'FINDOLOGIC1'
        ]);

        $mainProduct = $productId === $mainProductId
            ? $product
            : $this->createTestProduct([
                'id' => $mainProductId ?? Uuid::randomHex(),
                'productNumber' => 'FINDOLOGIC2'
            ]);
        $xmlItem = $withXmlItem ? new XMLItem($mainProduct->getId()) : null;

        $data = $this->defaultProductService->getDebugInformation(
            $product->getId(),
            self::VALID_SHOPKEY,
            $xmlItem,
            $mainProduct,
            new ExportErrors()
        )->getContent();

        return json_decode($data, true);
    }
}
