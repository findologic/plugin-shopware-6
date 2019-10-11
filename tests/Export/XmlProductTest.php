<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Export\XmlProduct;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class XmlProductTest extends TestCase
{
    use IntegrationTestBehaviour;

    /** @var Context */
    private $defaultContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultContext = Context::createDefaultContext();
    }

    public function testIfValidXMLProductIsCreated(): void
    {
        $shopkey = 'C4FE5E0DA907E9659D3709D8CFDBAE77';

        $productEntity = $this->createTestProduct();

        /** @var FindologicProduct|MockObject $findologicProductMock */
        $findologicProductMock = $this->getMockBuilder(FindologicProduct::class)->setConstructorArgs([
            $productEntity,
            $this->getContainer()->get('router'),
            $this->getContainer(),
            $this->defaultContext,
            $shopkey,
            []
        ])->getMock();
        $findologicProductMock->expects($this->atLeastOnce())->method('hasName');
        $findologicProductMock->expects($this->atLeastOnce())->method('hasAttributes');
        $findologicProductMock->expects($this->atLeastOnce())->method('hasPrices');
        $findologicProductMock->expects($this->atLeastOnce())->method('getName');
        $findologicProductMock->expects($this->atLeastOnce())->method('getAttributes');
        $findologicProductMock->expects($this->atLeastOnce())->method('getPrices');

        /** @var FindologicProductFactory|MockObject $findologicFactoryMock */
        $findologicFactoryMock = $this->getMockBuilder(FindologicProductFactory::class)->getMock();
        $findologicFactoryMock->expects($this->once())->method('buildInstance')->willReturn($findologicProductMock);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->once())->method('get')
            ->with(FindologicProductFactory::class)
            ->willReturn($findologicFactoryMock);

        $xmlProduct =
            new XmlProduct(
                $productEntity,
                $this->getContainer()->get('router'),
                $containerMock,
                $this->defaultContext,
                $shopkey,
                []
            );
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
