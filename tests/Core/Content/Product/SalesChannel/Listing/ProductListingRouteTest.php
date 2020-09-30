<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;

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
            $this->criteriaBuilderMock
        );
    }

    protected function getOriginal()
    {
        return $this->original;
    }
}
