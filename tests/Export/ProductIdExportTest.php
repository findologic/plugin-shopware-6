<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Storefront\Framework\Routing\Router;

class ProductIdExportTest extends XmlExportTest
{
    public function buildItemsAndAssertError(ProductEntity $product, CategoryEntity $category)
    {
        $exporter = $this->getExport();
        $items = $exporter->buildItems([$product], self::VALID_SHOPKEY, []);
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

        $items = $export->buildItems([], self::VALID_SHOPKEY, []);
        $this->assertEmpty($items);

        $errors = $export->getErrorHandler()->getExportErrors()->getGeneralErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('Product could not be found or is not available for search.', $errors[0]);
    }

    public function testProductCanNotBeExported(): void
    {
        $export = $this->getExport();
        $product = $this->createTestProduct(['categories' => []]);

        $items = $export->buildItems([$product], self::VALID_SHOPKEY, []);
        $response = $export->buildResponse($items, 0, 200);

        var_dump($response->getContent());
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
}
