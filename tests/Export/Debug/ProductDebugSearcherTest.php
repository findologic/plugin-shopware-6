<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export\Debug;

use FINDOLOGIC\FinSearch\Export\Debug\ProductDebugSearcher;
use FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder;
use FINDOLOGIC\FinSearch\Findologic\MainVariant;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\AssertionFailedError;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductDebugSearcherTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use ConfigHelper;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ProductCriteriaBuilder */
    private $productCriteriaBuilder;

    /** @var ProductDebugSearcher */
    private $defaultProductDebugSearcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $mockedConfig = $this->getFindologicConfig(['mainVariant' => 'default']);
        $mockedConfig->initializeBySalesChannel($this->salesChannelContext);

        $this->productCriteriaBuilder = new ProductCriteriaBuilder(
            $this->salesChannelContext,
            $this->getContainer()->get(SystemConfigService::class)
        );
        $this->defaultProductDebugSearcher = new ProductDebugSearcher(
            $this->salesChannelContext,
            $this->getContainer()->get('product.repository'),
            $this->productCriteriaBuilder,
            $mockedConfig
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

        $this->assertCount(1, $products);
        /** @var ProductEntity $product */
        $product = $products->first();

        $this->assertSame($product->getId(), $variantId1);
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
            $this->defaultProductDebugSearcher->getMainProductById($parentId)->getId()
        );
        $this->assertSame(
            $parentId,
            $this->defaultProductDebugSearcher->getMainProductById($variantId)->getId()
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
            $this->defaultProductDebugSearcher->getProductById($parentId)->getId()
        );
        $this->assertSame(
            $variantId,
            $this->defaultProductDebugSearcher->getProductById($variantId)->getId()
        );
    }

    public function testGetSiblings(): void
    {
        $parent = $this->createProductWithMultipleVariants();

        $this->assertSame(
            3,
            count($this->defaultProductDebugSearcher->getSiblings($parent->getId(), 100))
        );
        $this->assertSame(
            1,
            count($this->defaultProductDebugSearcher->getSiblings($parent->getId(), 1))
        );
    }
}
