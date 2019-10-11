<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use function current;

class FindologicProductTest extends TestCase
{
    use IntegrationTestBehaviour;

    /** @var ProductEntity|MockObject */
    private $productEntityMock;

    /** @var Context */
    private $defaultContext;

    /** @var string */
    private $shopkey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productEntityMock = $this->getMockBuilder(ProductEntity::class)->getMock();
        $this->defaultContext = Context::createDefaultContext();
        $this->shopkey = 'C4FE5E0DA907E9659D3709D8CFDBAE77';
    }

    public function productNameProvider()
    {
        return [
            'Product name is empty' => ['', ProductHasNoNameException::class],
            'Product name is null value' => [null, ProductHasNoNameException::class],
            'Product name is "Findologic Test"' => ['Findologic Test', null],
        ];
    }

    /**
     * @dataProvider productNameProvider
     */
    public function testProductName(?string $name, ?string $exception): void
    {
        if ($exception) {
            $this->expectException($exception);
        }

        $productEntity = $this->createTestProduct();
        $productEntity->setName($name);

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct =
            $findologicProductFactory->buildInstance(
                $productEntity,
                $this->getContainer()->get('router'),
                $this->getContainer(),
                $this->defaultContext,
                $this->shopkey,
                []
            );

        if (!$exception) {
            $this->assertTrue($findologicProduct->hasName());
            $this->assertSame($name, $findologicProduct->getName());
        } else {
            $this->assertFalse($findologicProduct->hasName());
        }
    }

    public function categoriesProvider()
    {
        return [
            'Product has no category' => [false, ProductHasNoCategoriesException::class],
            'Product has a category' => [true, null],
        ];
    }

    /**
     * @dataProvider categoriesProvider
     */
    public function testProductCategories(bool $hasCategory, ?string $exception): void
    {
        if ($exception) {
            $this->expectException($exception);
        }

        $productEntity = $this->createTestProduct();
        if (!$hasCategory) {
            $productEntity->setCategories(new CategoryCollection([]));
        }

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct =
            $findologicProductFactory->buildInstance(
                $productEntity,
                $this->getContainer()->get('router'),
                $this->getContainer(),
                $this->defaultContext,
                $this->shopkey,
                []
            );

        if (!$exception) {
            $this->assertTrue($findologicProduct->hasAttributes());
            $attribute = current($findologicProduct->getAttributes());
            $this->assertSame('cat_url', $attribute->getKey());
            $this->assertSame(sprintf('/navigation/%s', $productEntity->getId()), current($attribute->getValues()));
        }
    }

    public function priceProvider()
    {
        $price = new Price();
        $price->setValue(15);

        return [
            'Product has no prices' => [null, ProductHasNoPricesException::class],
            'Product has price set' => [$price, null]
        ];
    }

    /**
     * @dataProvider priceProvider
     */
    public function testProductPrices(?Price $price, ?string $exception)
    {
        if ($exception) {
            $this->expectException($exception);
        }

        $productEntity = $this->createTestProduct();
        if (!$price) {
            $productEntity->setPrice(new PriceCollection([]));
        }

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct =
            $findologicProductFactory->buildInstance(
                $productEntity,
                $this->getContainer()->get('router'),
                $this->getContainer(),
                $this->defaultContext,
                $this->shopkey,
                []
            );

        if (!$exception) {
            $this->assertTrue($findologicProduct->hasPrices());
            $this->assertEquals($price, current($findologicProduct->getPrices()));
        } else {
            $this->assertFalse($findologicProduct->hasPrices());
        }
    }

    private function createTestProduct(): ProductEntity
    {
        $id = Uuid::randomHex();

        $productData = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test name',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'FINDOLOGIC'],
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'categories' => [
                ['id' => $id, 'name' => 'Test Category'],
            ],
        ];

        $this->getContainer()->get('product.repository')->upsert([$productData], $this->defaultContext);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('categories');

        /** @var ProductEntity $product */
        $productEntity =
            $this->getContainer()->get('product.repository')->search($criteria, $this->defaultContext)->get($id);

        return $productEntity;
    }
}
