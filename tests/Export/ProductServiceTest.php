<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\AssertionFailedError;
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

    public function testIfMoreThanOneVariantExistsItWillUseVariantInformationInsteadOfMainProductInformation(): void
    {
        $expectedParentId = Uuid::randomHex();
        $expectedFirstVariantId = Uuid::randomHex();
        $expectedSecondVariantId = Uuid::randomHex();

        $this->createVisibleTestProduct([
            'id' => $expectedParentId,
            'active' => false
        ]);

        $variantDetails = [
            'stock' => 10,
            'active' => true,
            'parentId' => $expectedParentId,
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
        ];

        $this->createVisibleTestProduct(array_merge([
            'id' => $expectedFirstVariantId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
        ], $variantDetails));

        $this->createVisibleTestProduct(array_merge([
            'id' => $expectedSecondVariantId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
        ], $variantDetails));

        $products = $this->defaultProductService->searchVisibleProducts(20, 0);
        /** @var ProductEntity $product */
        $product = $products->first();

        // In the real world variants are created after another. When working with Shopware DAL manually,
        // sometimes the second statement may be executed before the first one, which causes a different result.
        // To prevent this test from failing if Shopware decides to create the second variant before the first one,
        // we ensure that the second variant is used instead.
        $expectedChildVariantId = $expectedSecondVariantId;
        try {
            $this->assertSame($expectedFirstVariantId, $product->getId());
        } catch (AssertionFailedError $e) {
            $this->assertSame($expectedSecondVariantId, $product->getId());
            $expectedChildVariantId = $expectedFirstVariantId;
        }

        $this->assertCount(2, $product->getChildren());

        foreach ($product->getChildren() as $child) {
            if (!$child->getParentId()) {
                $this->assertSame($expectedParentId, $child->getId());
            } else {
                $this->assertSame($expectedChildVariantId, $child->getId());
            }
        }
    }
}
