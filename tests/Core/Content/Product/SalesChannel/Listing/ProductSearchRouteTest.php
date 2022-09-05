<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use FINDOLOGIC\FinSearch\Storefront\Page\Search\SearchPageLoader;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Traits\SearchResultHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionObject;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;

class ProductSearchRouteTest extends ProductRouteBase
{
    use SalesChannelFunctionalTestBehaviour;
    use SearchResultHelper;
    use ProductHelper {
        ProductHelper::createCustomer insteadof SalesChannelFunctionalTestBehaviour;
    }

    /** @var AbstractProductSearchRoute|MockObject */
    private $original;

    /** @var SalesChannelContext| MockObject */
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
                'variantId' => 'a5a1c99e6fbf2316523151de9e1aad31',
                'expectedProductNumber' => 'FINDOLOGIC001.1'
            ],
            'Searching for second variant number' => [
                'query' => 'FINDOLOGIC001.2',
                'variantId' => 'edc0f84ed1e20dedff0ce81c1838758a',
                'expectedProductNumber' => 'FINDOLOGIC001.2'
            ],
            'Searching for product title' => [
                'query' => 'FINDOLOGIC Product',
                'variantId' => '29d554327a16fd51350688cfa9930b29',
                'expectedProductNumber' => 'FINDOLOGIC001'
            ],
            'Searching for first variant EAN' => [
                'query' => 'FL0011',
                'variantId' => 'a5a1c99e6fbf2316523151de9e1aad31',
                'expectedProductNumber' => 'FINDOLOGIC001.1'
            ],
            'Searching for second variant EAN' => [
                'query' => 'FL0012',
                'variantId' => 'edc0f84ed1e20dedff0ce81c1838758a',
                'expectedProductNumber' => 'FINDOLOGIC001.2'
            ],
            'Searching for first variant manufacturer number' => [
                'query' => 'MAN0011',
                'variantId' => 'a5a1c99e6fbf2316523151de9e1aad31',
                'expectedProductNumber' => 'FINDOLOGIC001.1'
            ],
            'Searching for second variant manufacturer number' => [
                'query' => 'MAN0012',
                'variantId' => 'edc0f84ed1e20dedff0ce81c1838758a',
                'expectedProductNumber' => 'FINDOLOGIC001.2'
            ],
        ];
    }

    /**
     * @dataProvider queryProvider
     */
    public function testSearchingVariantProductNumberWillShowVariant(
        string $query,
        string $variantId,
        string $expectedProductNumber
    ): void {
        $this->salesChannelContext = $this->getMockedSalesChannelContext(true);
        $product = $this->createTestProduct([], true);
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

        $this->addOptionsGroupAssociation($newCriteria);

        $variantCriteria = new Criteria();
        $variantCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('productNumber', $query),
            new EqualsFilter('ean', $query),
            new EqualsFilter('manufacturerNumber', $query),
        ]));
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

    public function testElasticSearchContextIsAddedForShopware64AndGreater(): void
    {
        $productSearchRouteMock = $this->getMockBuilder(ProductSearchRoute::class)
            ->setConstructorArgs([
                $this->original,
                $this->productSearchBuilderMock,
                $this->eventDispatcherMock,
                $this->productRepositoryMock,
                $this->productDefinition,
                $this->criteriaBuilder,
                $this->serviceConfigResourceMock,
                $this->findologicConfigServiceMock,
                $this->configMock
            ])
            ->onlyMethods(['addElasticSearchContext'])
            ->getMock();

        $productSearchRouteMock->expects($this->once())
            ->method('addElasticSearchContext');

        $salesChannelContextMock = $this->getMockedSalesChannelContext(true);
        $context = $salesChannelContextMock->getContext();

        $salesChannelContextMock->expects($this->any())->method('getContext')->willReturn($context);

        $request = new Request();
        $genericPageLoaderMock = $this->getMockBuilder(GenericPageLoader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $searchPageLoader = new SearchPageLoader(
            $genericPageLoaderMock,
            $productSearchRouteMock,
            $this->eventDispatcherMock
        );

        $searchPageLoader->load($request, $salesChannelContextMock);
    }
}
