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
}
