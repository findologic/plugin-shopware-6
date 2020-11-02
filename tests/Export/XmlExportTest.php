<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Router;

class XmlExportTest extends TestCase
{
    use ProductHelper;
    use SalesChannelHelper;
    use IntegrationTestBehaviour;

    protected const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    /** @var Logger */
    protected $logger;

    /** @var string[] */
    protected $crossSellCategories;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new Logger('fl_test_logger');
        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->crossSellCategories = [];
    }

    public function testWrapsItemProperly(): void
    {
        $product = $this->createVisibleTestProduct();

        $items = $this->getExport()->buildItems([$product], self::VALID_SHOPKEY, []);
        $this->assertCount(1, $items);
        $this->assertSame($product->getId(), $items[0]->getId());
    }

    public function testProductsInCrossSellCategoriesAreNotWrappedAndErrorIsLogged(): void
    {
        $productErrorHandler = new ProductErrorHandler();
        $this->logger->pushHandler($productErrorHandler);

        $product = $this->createVisibleTestProduct();

        $category = $product->getCategories()->first();
        $this->crossSellCategories = [$category->getId()];

        $items = $this->getExport()->buildItems([$product], self::VALID_SHOPKEY, []);
        $this->assertEmpty($items);

        $errors = $productErrorHandler->getExportErrors()->getProductError($product->getId())->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(
            sprintf(
                'Product with id %s (%s) was not exported because it is assigned to cross selling category %s (%s)',
                $product->getId(),
                $product->getName(),
                $category->getId(),
                implode(' > ', $category->getBreadcrumb())
            ),
            $errors[0]
        );
    }

    protected function getExport(): XmlExport
    {
        /** @var Router $router */
        $router = $this->getContainer()->get(Router::class);

        return new XmlExport(
            $router,
            $this->getContainer(),
            $this->logger,
            $this->crossSellCategories
        );
    }
}
