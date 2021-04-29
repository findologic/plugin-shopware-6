<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use OpenApi\Util;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionObject;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;

class ProductSearchRouteTest extends ProductRouteBase
{
    use SalesChannelFunctionalTestBehaviour;
    use ProductHelper;

    /** @var AbstractProductSearchRoute|MockObject */
    private $original;

    /**
     * @var MockObject|\Shopware\Core\System\SalesChannel\SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = $this->getMockBuilder(AbstractProductSearchRoute::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getRoute(): AbstractProductSearchRoute
    {
        return new ProductSearchRoute(
            $this->original,
            $this->productSearchBuilderMock,
            $this->eventDispatcherMock,
            $this->productRepositoryMock,
            $this->productDefinition,
            $this->criteriaBuilder,
            $this->serviceConfigResourceMock,
            $this->findologicConfigServiceMock,
            $this->configMock
        );
    }

    protected function getOriginal()
    {
        return $this->original;
    }

    public function queryProvider(): array
    {
        return [
            'Searching for first variant number' => [
                'query' => 'FINDOLOGIC001.1',
                'expectedProductNumber' => 'FINDOLOGIC001.1'
            ],
            'Searching for second variant number' => [
                'query' => 'FINDOLOGIC001.2',
                'expectedProductNumber' => 'FINDOLOGIC001.2'
            ],
            'Searching for product title' => [
                'query' => 'FINDOLOGIC Product',
                'expectedProductNumber' => 'FINDOLOGIC001'
            ],
        ];
    }

    /**
     * @dataProvider queryProvider
     */
    public function testSearchingVariantProductNumberWillShowVariant(string $query, string $expectedProductNumber): void
    {
        $this->salesChannelContext = $this->getMockedSalesChannelContext(true);
        $product = $this->createTestProduct([], true);
        $variantId = md5($query);
        $variant = $product->getChildren()->get($variantId);
        $context = $this->salesChannelContext->getContext();
        $originalCriteria = (new Criteria())->setIds([$product->getId()]);
        $newCriteria = clone $originalCriteria;
        $total = $variant ? 1 : 0;
        $searchResult = Utils::buildEntitySearchResult(
            ProductEntity::class,
            $total,
            new EntityCollection([$product]),
            null,
            $originalCriteria,
            $context
        );

        $variantCriteria = new Criteria();
        $variantCriteria->addFilter(new EqualsFilter('productNumber', $query));
        if ($variant) {
            $variantSearchResult = Utils::buildEntitySearchResult(
                ProductEntity::class,
                $total,
                new EntityCollection([$variant]),
                null,
                $variantCriteria,
                $context
            );

            $newCriteria->setIds([$variantId]);
        } else {
            $variantSearchResult = Utils::buildEntitySearchResult(
                ProductEntity::class,
                $total,
                new EntityCollection(),
                null,
                $variantCriteria,
                $context
            );
        }

        if ($variant) {
            $this->productRepositoryMock->method('search')->withConsecutive(
                [$variantCriteria, $this->salesChannelContext],
                [$newCriteria, $this->salesChannelContext]
            )->willReturn($variantSearchResult);
        } else {
            $this->productRepositoryMock->method('search')->withConsecutive(
                [$variantCriteria, $this->salesChannelContext],
                [$newCriteria, $this->salesChannelContext]
            )->willReturnOnConsecutiveCalls($variantSearchResult, $searchResult);
        }

        $route = $this->getRoute();
        $reflector = new ReflectionObject($route);
        $method = $reflector->getMethod('doSearch');
        $method->setAccessible(true);
        /** @var EntitySearchResult $result */
        $result = $method->invoke($route, $originalCriteria, $this->salesChannelContext, $query);
        /** @var ProductEntity $searchedProduct */
        $searchedProduct = $result->first();
        $this->assertSame($expectedProductNumber, $searchedProduct->getProductNumber());
    }
}
