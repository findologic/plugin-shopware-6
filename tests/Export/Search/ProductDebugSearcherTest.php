<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Debug;

use FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder;
use FINDOLOGIC\FinSearch\Export\Search\ProductDebugSearcher;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ServicesHelper;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductDebugSearcherTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use ConfigHelper;
    use ServicesHelper;

    private SalesChannelContext $salesChannelContext;

    private ExportContext $exportContext;

    private ProductCriteriaBuilder $productCriteriaBuilder;

    private ProductDebugSearcher $defaultProductDebugSearcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->exportContext = $this->getExportContext(
            $this->salesChannelContext,
            $this->getCategory($this->salesChannelContext->getSalesChannel()->getNavigationCategoryId())
        );

        $this->productCriteriaBuilder = new ProductCriteriaBuilder($this->exportContext, $this->getPluginConfig());
        $this->defaultProductDebugSearcher = new ProductDebugSearcher(
            $this->salesChannelContext,
            $this->getContainer()->get('product.repository'),
            $this->productCriteriaBuilder,
            $this->exportContext,
            $this->getPluginConfig()
        );
    }

    public function testFindsCorrectVariantWhenMainIdProvided(): void
    {
        $productId = Uuid::randomHex();
        $variantId1 = Uuid::randomHex();
        $this->createVisibleTestProductWithCustomVariants(
            ['id' => $productId],
            [
                $this->getBasicVariantData([
                    'id' => $variantId1,
                    'parentId' => $productId,
                    'productNumber' => 'FINDOLOGIC001.1',
                    'name' => 'FINDOLOGIC VARIANT 1',
                ])
            ]
        );

        $products = $this->defaultProductDebugSearcher->findVisibleProducts(null, null, $productId);
        $product = $products->first();

        $this->assertCount(1, $products);
        $this->assertSame($product->id, $variantId1);
    }

    public function testGetMainProductById(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $this->createVisibleTestProductWithCustomVariants(
            ['id' => $parentId],
            [
                $this->getBasicVariantData([
                    'id' => $variantId,
                    'parentId' => $parentId,
                    'productNumber' => 'FINDOLOGIC001.1',
                    'name' => 'FINDOLOGIC VARIANT 1',
                ])
            ]
        );

        $this->assertSame(
            $parentId,
            $this->defaultProductDebugSearcher->getMainProductById($parentId)->id
        );
        $this->assertSame(
            $parentId,
            $this->defaultProductDebugSearcher->getMainProductById($variantId)->id
        );
    }

    public function testGetProductById(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $this->createVisibleTestProductWithCustomVariants(
            ['id' => $parentId],
            [
                $this->getBasicVariantData([
                    'id' => $variantId,
                    'parentId' => $parentId,
                    'productNumber' => 'FINDOLOGIC001.1',
                    'name' => 'FINDOLOGIC VARIANT 1',
                ])
            ]
        );

        $this->assertSame(
            $parentId,
            $this->defaultProductDebugSearcher->getProductById($parentId)->id
        );
        $this->assertSame(
            $variantId,
            $this->defaultProductDebugSearcher->getProductById($variantId)->id
        );
    }

    public function testGetSiblings(): void
    {
        $parent = $this->createProductWithMultipleVariants();

        $this->assertSame(
            3,
            count($this->defaultProductDebugSearcher->getSiblings($parent->id, 100))
        );
        $this->assertSame(
            1,
            count($this->defaultProductDebugSearcher->getSiblings($parent->id, 1))
        );
    }
}
