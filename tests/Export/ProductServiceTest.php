<?php

declare(strict_types=1);

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

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ProductService */
    private $defaultProductService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->defaultProductService = ProductService::getInstance(
            $this->getContainer(),
            $this->salesChannelContext
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

        $actualProductService = ProductService::getInstance($this->getContainer(), $this->salesChannelContext);
        $this->assertSame($productService, $actualProductService);
        $this->assertInstanceOf(SalesChannelContext::class, $productService->getSalesChannelContext());
        $this->assertSame($this->salesChannelContext, $productService->getSalesChannelContext());
    }

    public function testFindsVariantForInactiveProduct(): void
    {
        // Main product is inactive.
        $inactiveProduct = $this->createVisibleTestProduct([
            'active' => false
        ]);

        $this->createVisibleTestProduct([
            'id' => Uuid::randomHex(),
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT',
            'stock' => 10,
            'active' => true,
            'parentId' => $inactiveProduct->getId(),
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
        ]);

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);
        $product = $products->first();

        $this->assertSame('FINDOLOGIC VARIANT', $product->getName());
    }
}
