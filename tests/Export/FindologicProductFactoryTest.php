<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use FINDOLOGIC\FinSearch\Tests\ProductHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FindologicProductFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;

    /** @var Context */
    private $defaultContext;

    /** @var string */
    private $shopkey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultContext = Context::createDefaultContext();
        $this->shopkey = '80AB18D4BE2654E78244106AD315DC2C';
    }

    public function testBuildInstance(): void
    {
        $productEntity = $this->createTestProduct();

        $this->assertInstanceOf(ProductEntity::class, $productEntity);

        /** @var FindologicProductFactory $findologicProductFactory */
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

        $this->assertInstanceOf(FindologicProduct::class, $findologicProduct);
    }
}
