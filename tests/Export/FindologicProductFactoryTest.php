<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FindologicProductFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;
    use ConfigHelper;
    use SalesChannelHelper;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var string */
    private $shopkey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->shopkey = $this->getShopkey();

        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
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
            $this->salesChannelContext->getContext(),
            $this->shopkey,
            [],
            new XMLItem('123')
        );

        $this->assertInstanceOf(FindologicProduct::class, $findologicProduct);
    }
}
