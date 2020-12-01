<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Symfony\Component\HttpFoundation\Request;

class ProductListingRouteTest extends ProductRouteBase
{
    /** @var AbstractProductListingRoute|MockObject */
    private $original;

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
            $this->eventDispatcherMock,
            $this->productDefinitionMock,
            $this->criteriaBuilderMock,
            $this->serviceConfigResourceMock,
            $this->findologicConfigServiceMock,
            $this->configMock
        );
    }

    protected function getOriginal()
    {
        return $this->original;
    }

    public function testWillUseOriginalInCaseTheCategoryIdIsTheMainCategory(): void
    {
        $expectedMainCategoryId = '69';

        $salesChannelContextMock = $this->getMockedSalesChannelContext(true, $expectedMainCategoryId);
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $productRoute = $this->getRoute();

        $this->getOriginal()->expects($this->once())->method('load');
        $this->call($productRoute, $request, $salesChannelContextMock, $expectedMainCategoryId);
    }
}
