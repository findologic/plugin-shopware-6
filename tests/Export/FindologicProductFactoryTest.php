<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class FindologicProductFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /** @var Context */
    private $defaultContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultContext = Context::createDefaultContext();
    }

    public function testBuildInstance(): void
    {
        $shopkey = 'C4FE5E0DA907E9659D3709D8CFDBAE77';
        $id = Uuid::randomHex();

        $data = [
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

        $this->getContainer()->get('product.repository')->upsert([$data], $this->defaultContext);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('categories');

        /** @var ProductEntity $product */
        $productEntity = $this->getContainer()
            ->get('product.repository')
            ->search($criteria, $this->defaultContext)
            ->get($id);

        $this->assertInstanceOf(ProductEntity::class, $productEntity);

        $findologicProductFactory = new FindologicProductFactory();
        $findologicProduct =
            $findologicProductFactory->buildInstance(
                $productEntity,
                $this->getContainer()->get('router'),
                $this->getContainer(),
                $this->defaultContext,
                $shopkey,
                []
            );

        $this->assertInstanceOf(FindologicProduct::class, $findologicProduct);
    }
}
