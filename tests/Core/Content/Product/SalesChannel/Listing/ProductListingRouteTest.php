<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionObject;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class ProductListingRouteTest extends ProductRouteBase
{
    private AbstractProductListingRoute|MockObject $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = $this->getMockBuilder(AbstractProductListingRoute::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getRoute(): AbstractProductListingRoute
    {
        return new ProductListingRoute(
            $this->original,
            $this->productRepositoryMock,
            $this->categoryRepositoryMock,
            $this->productStreamBuilderMock,
            $this->eventDispatcherMock,
            $this->productDefinition,
            $this->criteriaBuilder,
            $this->serviceConfigResourceMock,
            $this->findologicConfigServiceMock,
            $this->configMock
        );
    }

    protected function getOriginal(): AbstractProductListingRoute
    {
        return $this->original;
    }

    public function testWillUseOriginalInCaseTheCategoryIdIsTheMainCategory(): void
    {
        $expectedMainCategoryId = Uuid::randomHex();

        $salesChannelContextMock = $this->getMockedSalesChannelContext(true, $expectedMainCategoryId);
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $this->setCategoryMock($expectedMainCategoryId);

        $productRoute = $this->getRoute();

        $this->getOriginal()->expects($this->once())->method('load');
        $this->call($productRoute, $request, $salesChannelContextMock, $expectedMainCategoryId);
    }

    public function requestFromHomePageProvider(): array
    {
        $sessionMock = $this->getSessionMock();
        $homePageRequest = Request::create('http://your-shop.de/');
        $homePageRequest->setSession($sessionMock);

        $requestWithReferer = Request::create('http://your-shop.de/filters');
        $requestWithReferer->headers->set('X-Requested-With', 'XMLHttpRequest');
        $requestWithReferer->headers->set('referer', 'http://your-shop.de/');
        $requestWithReferer->setSession($sessionMock);

        return [
            'Request to homepage' => [
                'request' => $homePageRequest
            ],
            'Filter request from homepage' => [
                'request' => $requestWithReferer
            ]
        ];
    }

    /**
     * @dataProvider requestFromHomePageProvider
     */
    public function testWillUseOriginalInCaseRequestComesFromHomepage(Request $request): void
    {
        $salesChannelContextMock = $this->getMockedSalesChannelContext(true, '1');
        $productRoute = $this->getRoute();

        $this->getOriginal()->expects($this->once())->method('load');
        $this->call($productRoute, $request, $salesChannelContextMock, '2');
    }

    public function testCategoryWithManualProductSelectionAddsCategoryFilter(): void
    {
        $categoryId = Uuid::randomHex();

        $salesChannelContextMock = $this->getMockedSalesChannelContext(true, '1');
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $criteria = new Criteria();

        $this->setCategoryMock($categoryId);

        $productRoute = $this->getRoute();
        /** @var ProductListingRouteResponse $response */
        $response = $this->call($productRoute, $request, $salesChannelContextMock, $categoryId, $criteria);

        $this->assertTrue($response->getResult()->getCriteria()->hasEqualsFilter('product.categoriesRo.id'));
    }

    public function testCategoryWithDynamicProductGroupAddsStreamIdAndFilters(): void
    {
        $supportsProductStreams = defined(
            '\Shopware\Core\Content\Category\CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM'
        );
        if (!$supportsProductStreams) {
            $this->markTestSkipped('Dynamic product groups for categories where introduced in Shopware 6.3.1.0');
        }

        $categoryId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $salesChannelContextMock = $this->getMockedSalesChannelContext(true, '1');
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $criteria = new Criteria();
        $expectedStockFilter = new EqualsFilter('product.stock', 30);
        $expectedManufacturerFilter = new EqualsFilter('product.manufacturer.id', 10);
        $expectedFilters = [$expectedStockFilter, $expectedManufacturerFilter];

        $this->setCategoryMock(
            $categoryId,
            CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
            $streamId
        );

        $this->productStreamBuilderMock->expects($this->once())
            ->method('buildFilters')
            ->willReturn($expectedFilters);

        $productRoute = $this->getRoute();
        /** @var ProductListingRouteResponse $response */
        $response = $this->call($productRoute, $request, $salesChannelContextMock, $categoryId, $criteria);

        if (method_exists($response->getResult(), 'getStreamId')) {
            $this->assertEquals($streamId, $response->getResult()->getStreamId());
        }

        foreach ($expectedFilters as $expectedFilter) {
            $this->assertContains($expectedFilter, $response->getResult()->getCriteria()->getFilters());
        }
    }

    public function testOffsetIsResetBeforeDatabaseSearch(): void
    {
        $productRoute = $this->getRoute();
        $criteria = new Criteria(['1', '2', '3', '4']);
        $criteria->setOffset(24);

        $reflector = new ReflectionObject($productRoute);
        $method = $reflector->getMethod('doSearch');
        $method->invoke($productRoute, $criteria, $this->getMockedSalesChannelContext(true, '1'));

        $this->assertNull($criteria->getOffset());
    }
}
