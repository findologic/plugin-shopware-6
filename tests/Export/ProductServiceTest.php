<?php

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;

    /** @var SalesChannelContext|MockObject */
    private $salesChannelContextMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContextMock = $this->buildSalesChannelContext();
    }

    public function testFindsAllProducts(): void
    {
        $expectedProduct = $this->createTestProduct();

        $service = $this->getDefaultProductService();
        $products = $service->searchAllProducts(20, 0);

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($expectedProduct->getId(), $product->getId());
    }

    public function testFindsProductsAvailableForSearch(): void
    {
        $expectedProduct = $this->createTestProduct([
            'visibilities' => [
                [
                    'id' => Uuid::randomHex(),
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => 20
                ]
            ]
        ]);

        $service = $this->getDefaultProductService();
        $products = $service->searchVisibleProducts(20, 0);

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($expectedProduct->getId(), $product->getId());
    }

    private function getDefaultProductService(): ProductService
    {
        return ProductService::getInstance($this->getContainer(), $this->salesChannelContextMock);
    }
}
