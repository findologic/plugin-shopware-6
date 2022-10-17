<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Monolog\Logger;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class XmlExportTest extends TestCase
{
    use ProductHelper;
    use SalesChannelHelper;
    use IntegrationTestBehaviour;

    /** @var Logger */
    protected $logger;

    /** @var string[] */
    protected $crossSellCategories;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    /** @var ProductErrorHandler */
    protected $productErrorHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productErrorHandler = new ProductErrorHandler();
        $this->logger = new Logger('fl_test_logger');
        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->crossSellCategories = [];

        DynamicProductGroupService::getInstance(
            $this->getContainer(),
            $this->getContainer()->get('serializer.mapping.cache.symfony'),
            Context::createDefaultContext(),
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            0
        );

        $this->getContainer()->set('fin_search.export_context', new ExportContext(
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            $this->getCategory()
        ));

        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->logger->pushHandler($this->productErrorHandler);
    }

    public function testWrapsItemProperly(): void
    {
        $product = $this->createTestProduct();

        $items = $this->getExport()->buildItems([$product]);
        if (Utils::versionGreaterOrEqual('6.3.0')) {
            // Domain is not generated for older versions
            $this->getExport()->buildResponse($items, 0, 1);
        }

        $this->assertCount(1, $items);
        $this->assertSame($product->getId(), $items[0]->getId());
    }

    public function testManuallyAssignedProductsInCrossSellCategoriesAreNotWrappedAndErrorIsLogged(): void
    {
        $product = $this->createTestProduct(['productNumber' => 'FINDOLOGIC1']);

        $category = $product->getCategories()->first();
        $this->crossSellCategories = [$category->getId()];

        $this->buildItemsAndAssertError($product, $category);
    }

    public function testProductsInDynamicProductGroupCrossSellCategoriesAreNotWrappedAndErrorIsLogged(): void
    {
        $product = $this->createTestProduct(['productNumber' => 'FINDOLOGIC1']);

        $category = $this->createTestCategory();
        $this->crossSellCategories = [$category->getId()];

        $dynamicProductGroupServiceMock = $this->getMockBuilder(DynamicProductGroupService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dynamicProductGroupServiceMock->expects($this->any())
            ->method('getCategories')
            ->willReturn([$category->getId() => $category]);

        $this->getContainer()->set('fin_search.dynamic_product_group', $dynamicProductGroupServiceMock);

        $this->buildItemsAndAssertError($product, $category);
    }

    public function buildItemsAndAssertError(ProductEntity $product, CategoryEntity $category)
    {
        $items = $this->getExport()->buildItems([$product]);
        $this->getExport()->buildResponse($items, 0, 1);
        $this->assertEmpty($items);

        $errors = $this->productErrorHandler->getExportErrors()->getProductError($product->getId())->getErrors();
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

    public function testKeywordsAreNotRequired(): void
    {
        $product = $this->createVisibleTestProduct(['tags' => []]);

        $items = $this->getExport()->buildItems([$product]);
        if (Utils::versionGreaterOrEqual('6.3.0')) {
            // Domain is not generated for older versions
            $this->getExport()->buildResponse($items, 0, 1);
        }

        $this->assertCount(1, $items);
        $this->assertSame($product->getId(), $items[0]->getId());
    }

    public function testFallbackToValidVariant(): void
    {
        $product = $this->createProductWithMultipleVariants();
        $product->setCategories(new CategoryCollection());

        $items = $this->getExport()->buildItems([$product]);
        $this->getExport()->buildResponse($items, 0, 1);

        $this->assertCount(1, $items);
        $this->assertNotEquals($product->getId(), $items[0]->getId());
    }

    protected function getExport(): XmlExport
    {
        /** @var Router $router */
        $router = $this->getContainer()->get(Router::class);
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        return new XmlExport(
            $router,
            $this->getContainer(),
            $this->logger,
            $eventDispatcher,
            $this->crossSellCategories
        );
    }
}
