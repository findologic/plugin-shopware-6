<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Tests\ProductHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FindologicProductTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;

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
        $this->shopkey = '80AB18D4BE2654E78244106AD315DC2C';
    }

    public function productNameProvider(): array
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

    public function categoriesProvider(): array
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

    public function priceProvider(): array
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
    public function testProductPrices(?Price $price, ?string $exception): void
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
}
