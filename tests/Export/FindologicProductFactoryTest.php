<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use FINDOLOGIC\FinSearch\Tests\Traits\ConfigHelperTrait;
use FINDOLOGIC\FinSearch\Tests\Traits\ProductHelperTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FindologicProductFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelperTrait;
    use ConfigHelperTrait;

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
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function testBuildInstance(): void
    {
        $productEntity = $this->createTestProduct();
        $this->assertInstanceOf(ProductEntity::class, $productEntity);

        /** @var FindologicProductFactory $findologicProductFactory */
        $findologicProductFactory = new FindologicProductFactory();

        $findologicProduct = $findologicProductFactory->buildInstance(
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
