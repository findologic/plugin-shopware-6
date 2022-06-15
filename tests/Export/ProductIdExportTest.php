<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\ProductServiceSeparateVariants;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Framework\Routing\Router;

class ProductIdExportTest extends XmlExportTest
{
    use SalesChannelHelper;

    protected function setUp(): void
    {
        parent::setUp();

        DynamicProductGroupService::getInstance(
            $this->getContainer(),
            $this->getContainer()->get('serializer.mapping.cache.symfony'),
            Context::createDefaultContext(),
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            0
        );
        $this->getContainer()->set(
            'fin_search.export_context',
            new ExportContext(
                'ABCDABCDABCDABCDABCDABCDABCDABCD',
                [],
                $this->getCategory()
            )
        );

        $this->salesChannelContext = $this->buildSalesChannelContext(
            Defaults::SALES_CHANNEL,
            'http://test.de'
        );

        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
    }

    public function buildItemsAndAssertError(ProductEntity $product, CategoryEntity $category): void
    {
        $product = $this->createVisibleTestProduct();

        $category = $product->getCategories()->first();
        $this->crossSellCategories = [$category->getId()];

        $exporter = $this->getExport();
        $items = $exporter->buildItems([$product]);
        $this->assertEmpty($items);

        $errors = $exporter->getErrorHandler()->getExportErrors()->getProductError($product->getId())->getErrors();
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

    public function testWarnsIfNoProductsAreReceived(): void
    {
        $export = $this->getExport();

        $items = $export->buildItems([]);
        $this->assertEmpty($items);

        $errors = $export->getErrorHandler()->getExportErrors()->getGeneralErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('Product could not be found or is not available for search.', $errors[0]);
    }

    public function testProductCanNotBeExported(): void
    {
        $export = $this->getExport();
        $product = $this->createTestProduct(['categories' => []]);

        $items = $export->buildItems([$product]);
        $response = $export->buildResponse($items, 0, 200);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('content-type'));
        $errors = json_decode($response->getContent(), true);

        $expectedName = Utils::versionGreaterOrEqual('6.4.11.0')
            ? 'FINDOLOGIC Product EN'
            : 'FINDOLOGIC Product';
        $expectedErrors = [
            'general' => [],
            'products' => [
                [
                    'id' => $product->getId(),
                    'errors' => [
                        sprintf(
                            'Product "%s" with id %s was not exported because it has no categories assigned',
                            $expectedName,
                            $product->getId()
                        )
                    ]
                ]
            ]
        ];

        $this->assertSame($expectedErrors, $errors);
    }

    /**
     * @return ProductIdExport
     */
    protected function getExport(): XmlExport
    {
        /** @var Router $router */
        $router = $this->getContainer()->get(Router::class);

        return new ProductIdExport(
            $router,
            $this->getContainer(),
            $this->logger,
            $this->crossSellCategories
        );
    }

    public function getCategory(): CategoryEntity
    {
        $categoryRepo = $this->getContainer()->get('category.repository');
        $categories = $categoryRepo->search(new Criteria(), Context::createDefaultContext());

        /** @var CategoryEntity $expectedCategory */
        return $categories->first();
    }
}
