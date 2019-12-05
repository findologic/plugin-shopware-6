<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\Export\Data\DateAdded;
use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Exceptions\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Export\XmlProduct;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use FINDOLOGIC\FinSearch\Tests\Traits\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\ProductHelper;
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
    use ConfigHelper;

    /** @var Context */
    private $defaultContext;

    /** @var string */
    private $shopkey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultContext = Context::createDefaultContext();
        $this->shopkey = $this->getShopkey();
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoAttributesException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testIfValidXMLProductIsCreated(): void
    {
        $productEntity = $this->createTestProduct();

        /** @var FindologicProduct|MockObject $findologicProductMock */
        $findologicProductMock = $this->getMockBuilder(FindologicProduct::class)->setConstructorArgs([
            $productEntity,
            $this->getContainer()->get('router'),
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
            []
        ])->getMock();
        $findologicProductMock->expects($this->once())->method('hasName')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getName')->willReturn('some name');
        $findologicProductMock->expects($this->once())->method('hasAttributes')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getAttributes')->willReturn([]);
        $findologicProductMock->expects($this->once())->method('hasPrices')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getPrices')->willReturn([]);
        $findologicProductMock->expects($this->once())->method('hasDescription')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getDescription')->willReturn('some description');
        $findologicProductMock->expects($this->once())->method('hasDateAdded')->willReturn(true);
        $dateAdded = new DateAdded();
        $dateAdded->setDateValue($productEntity->getCreatedAt());
        $findologicProductMock->expects($this->once())->method('getDateAdded')->willReturn($dateAdded);
        $findologicProductMock->expects($this->once())->method('hasUrl')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getUrl')->willReturn('some url');
        $findologicProductMock->expects($this->once())->method('hasKeywords')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getKeywords')->willReturn([]);
        $findologicProductMock->expects($this->once())->method('hasImages')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getImages')->willReturn([]);
        $findologicProductMock->expects($this->once())->method('getSalesFrequency')->willReturn(1);
        $findologicProductMock->expects($this->once())->method('hasUserGroups')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getUserGroups')->willReturn([]);
        $findologicProductMock->expects($this->once())->method('hasOrdernumbers')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getOrdernumbers')->willReturn([]);
        $findologicProductMock->expects($this->once())->method('hasProperties')->willReturn(true);
        $findologicProductMock->expects($this->once())->method('getProperties')->willReturn([]);

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
            $this->shopkey,
            []
        );

        $this->assertInstanceOf(Item::class, $xmlItem->getXmlItem());
    }

    /**
     * @throws AccessEmptyPropertyException
     * @throws ProductHasNoAttributesException
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testAttributeException(): void
    {
        $this->expectException(ProductHasNoAttributesException::class);

        /** @var ProductEntity $productEntity */
        $productEntity = $this->createTestProduct();

        /** @var FindologicProduct|MockObject $findologicProductMock */
        $findologicProductMock = $this->getMockBuilder(FindologicProduct::class)->setConstructorArgs([
            $productEntity,
            $this->getContainer()->get('router'),
            $this->getContainer(),
            $this->defaultContext,
            $this->shopkey,
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
            $this->shopkey,
            []
        );
    }
}
