<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use Shopware\Storefront\Framework\Routing\Router;

class ProductIdExportTest extends XmlExportTest
{
    public function testProductsInCrossSellCategoriesAreNotWrappedAndErrorIsLogged(): void
    {
        $product = $this->createVisibleTestProduct();

        $category = $product->getCategories()->first();
        $this->crossSellCategories = [$category->getId()];

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
        $product = $this->createTestProduct(['name' => ' ']);

        $items = $export->buildItems([$product], self::VALID_SHOPKEY, []);
        $response = $export->buildResponse($items, 0, 200);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('content-type'));
        $errors = json_decode($response->getContent(), true);

        $expectedErrors = [
            'general' => [],
            'products' => [
                [
                    'id' => $product->getId(),
                    'errors' => [
                        sprintf('Product with id %s was not exported because it has no name set', $product->getId())
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
