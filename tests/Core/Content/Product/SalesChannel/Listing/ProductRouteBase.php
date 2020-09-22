<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class ProductRouteBase extends TestCase
{
    /**
     * @var EventDispatcherInterface|MockObject
     */
    protected $eventDispatcherMock;

    /**
     * @var ProductDefinition|MockObject
     */
    protected $productDefinitionMock;

    /**
     * @var RequestCriteriaBuilder|MockObject
     */
    protected $criteriaBuilderMock;

    /**
     * @var SalesChannelRepositoryInterface|MockObject
     */
    protected $productRepositoryMock;

    /**
     * @var ProductSearchBuilderInterface
     */
    protected $productSearchBuilderMock;

    protected function setUp(): void
    {
        $this->eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->productDefinitionMock = $this->getMockBuilder(ProductDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->criteriaBuilderMock = $this->getMockBuilder(RequestCriteriaBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->productRepositoryMock = $this->getMockBuilder(SalesChannelRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->productSearchBuilderMock = $this->getMockBuilder(ProductSearchBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return AbstractProductListingRoute|AbstractProductSearchRoute
     */
    abstract protected function getRoute();

    /**
     * @return AbstractProductListingRoute|AbstractProductSearchRoute|MockObject
     */
    abstract protected function getOriginal();

    /**
     * @return SalesChannelContext|MockObject
     */
    protected function getMockedSalesChannelContext(bool $findologicActive): SalesChannelContext
    {
        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var Context|MockObject $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $findologicEnabled = $this->getMockBuilder(FindologicEnabled::class)
            ->disableOriginalConstructor()
            ->getMock();

        $salesChannelContextMock->expects($this->any())->method('getContext')->willReturn($context);
        $context->expects($this->any())->method('getExtension')
            ->with('flEnabled')
            ->willReturn($findologicEnabled);
        $findologicEnabled->expects($this->any())->method('getEnabled')->willReturn($findologicActive);

        return $salesChannelContextMock;
    }

    public function testFindologicHandlesRequestWhenActive(): void
    {
        $salesChannelContextMock = $this->getMockedSalesChannelContext(true);
        $request = Request::create('http://your-shop.de/some-category');

        $productRoute = $this->getRoute();

        $this->getOriginal()->expects($this->never())->method('load');
        $this->call($productRoute, $request, $salesChannelContextMock);
    }

    public function testShopwareHandlesRequestWhenFindologicisInactive(): void
    {
        $salesChannelContextMock = $this->getMockedSalesChannelContext(false);
        $request = Request::create('http://your-shop.de/some-category');

        $productRoute = $this->getRoute();

        $this->getOriginal()->expects($this->once())->method('load');
        $this->call($productRoute, $request, $salesChannelContextMock);
    }

    private function call($productRoute, Request $request, SalesChannelContext $salesChannelContext): void
    {
        if ($productRoute instanceof AbstractProductListingRoute) {
            $productRoute->load('69', $request, $salesChannelContext);
        } elseif ($productRoute instanceof AbstractProductSearchRoute) {
            /** @var $productRoute AbstractProductSearchRoute */
            $productRoute->load($request, $salesChannelContext);
        } else {
            throw new InvalidArgumentException('Unknown productRoute of class %s', get_class($productRoute));
        }
    }
}
