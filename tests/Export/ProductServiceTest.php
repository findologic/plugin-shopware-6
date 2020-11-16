<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;

    /** @var SalesChannelContext|MockObject */
    private $salesChannelContextMock;

    /** @var ProductService */
    private $defaultProductService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContextMock = $this->buildSalesChannelContext();
        $this->defaultProductService = ProductService::getInstance(
            $this->getContainer(),
            $this->salesChannelContextMock
        );
    }

    public function testFindsAllProducts(): void
    {
        $expectedProduct = $this->createTestProduct();

        $products = $this->defaultProductService->searchAllProducts(20, 0);

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($expectedProduct->getId(), $product->getId());
    }

    public function testFindsProductsAvailableForSearch(): void
    {
        $expectedProduct = $this->createVisibleTestProduct();

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($expectedProduct->getId(), $product->getId());
    }

    public function testFindsProductId(): void
    {
        $expectedProduct = $this->createVisibleTestProduct();

        $products = $this->defaultProductService->searchVisibleProducts(20, 0, $expectedProduct->getId());

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($expectedProduct->getId(), $product->getId());
    }

    public function testGetInstancePopulatesSalesChannelContext(): void
    {
        $productService = new ProductService($this->getContainer());
        $this->getContainer()->set(ProductService::CONTAINER_ID, $productService);

        $this->assertNull($productService->getSalesChannelContext());

        $actualProductService = ProductService::getInstance($this->getContainer(), $this->salesChannelContextMock);
        $this->assertSame($productService, $actualProductService);
        $this->assertInstanceOf(SalesChannelContext::class, $productService->getSalesChannelContext());
        $this->assertSame($this->salesChannelContextMock, $productService->getSalesChannelContext());
    }
}
