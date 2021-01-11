<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;

class ProductSearchRouteTest extends ProductRouteBase
{
    /** @var AbstractProductSearchRoute|MockObject  */
    private $original;

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
}
