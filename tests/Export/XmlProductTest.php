<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Export\XmlProduct;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use FINDOLOGIC\FinSearch\Tests\ProductHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class XmlProductTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;

    /** @var Context */
    private $defaultContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultContext = Context::createDefaultContext();
    }

    public function testIfValidXMLProductIsCreated(): void
    {
        $shopkey = '80AB18D4BE2654E78244106AD315DC2C';

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
        $findologicProductMock->expects($this->once())->method('hasName')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getName')->willReturn($productEntity->getName());
        $findologicProductMock->expects($this->once())->method('hasAttributes')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getAttributes')->willReturn([]);
        $findologicProductMock->expects($this->once())->method('hasPrices')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getPrices')->willReturn([]);

        /** @var FindologicProductFactory|MockObject $findologicFactoryMock */
        $findologicFactoryMock = $this->getMockBuilder(FindologicProductFactory::class)->getMock();
        $findologicFactoryMock->expects($this->once())->method('buildInstance')->willReturn($findologicProductMock);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->once())->method('get')
            ->with(FindologicProductFactory::class)
            ->willReturn($findologicFactoryMock);

        $xmlItem = new XmlProduct(
            $productEntity,
            $this->getContainer()->get('router'),
            $containerMock,
            $this->defaultContext,
            $shopkey,
            []
        );

        $this->assertInstanceOf(Item::class, $xmlItem->getXmlItem());
    }

    public function testAttributeException(): void
    {
        $this->expectException(ProductHasNoAttributesException::class);

        $shopkey = '80AB18D4BE2654E78244106AD315DC2C';

        /** @var ProductEntity $productEntity */
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
        $findologicProductMock->expects($this->once())->method('hasName')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getName')->willReturn($productEntity->getName());
        $findologicProductMock->expects($this->once())->method('hasAttributes')->willReturn(false);
        $findologicProductMock->expects($this->never())->method('getAttributes');
        $findologicProductMock->expects($this->never())->method('hasPrices');
        $findologicProductMock->expects($this->never())->method('getPrices');

        /** @var FindologicProductFactory|MockObject $findologicFactoryMock */
        $findologicFactoryMock = $this->getMockBuilder(FindologicProductFactory::class)->getMock();
        $findologicFactoryMock->expects($this->once())->method('buildInstance')->willReturn($findologicProductMock);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->once())->method('get')
            ->with(FindologicProductFactory::class)
            ->willReturn($findologicFactoryMock);

        new XmlProduct(
            $productEntity,
            $this->getContainer()->get('router'),
            $containerMock,
            $this->defaultContext,
            $shopkey,
            []
        );
    }
}
