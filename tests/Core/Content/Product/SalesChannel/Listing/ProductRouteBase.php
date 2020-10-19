<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
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
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

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

    /**
     * @var ServiceConfigResource|MockObject
     */
    protected $serviceConfigResourceMock;

    /**
     * @var SystemConfigService|MockObject
     */
    protected $systemConfigServiceMock;

    /**
     * @var Config|MockObject
     */
    protected $configMock;

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

        $this->serviceConfigResourceMock = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->systemConfigServiceMock = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configMock->expects($this->any())->method('isInitialized')->willReturn(true);
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
    protected function getMockedSalesChannelContext(
        bool $findologicActive,
        string $categoryId = ''
    ): SalesChannelContext {
        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelMock->expects($this->any())->method('getNavigationCategoryId')->willReturn($categoryId);

        $salesChannelContextMock->expects($this->any())->method('getSalesChannel')
            ->willReturn($salesChannelMock);

        /** @var Context|MockObject $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $findologicService = $this->getMockBuilder(FindologicService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $salesChannelContextMock->expects($this->any())->method('getContext')->willReturn($context);
        $context->expects($this->any())->method('getExtension')
            ->with('findologicService')
            ->willReturn($findologicService);
        $findologicService->expects($this->any())->method('getEnabled')->willReturn($findologicActive);

        return $salesChannelContextMock;
    }

    /**
     * @return Session|MockObject
     */
    protected function getSessionMock(): Session
    {
        return $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testFindologicHandlesRequestWhenActive(): void
    {
        $salesChannelContextMock = $this->getMockedSalesChannelContext(true);
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $productRoute = $this->getRoute();

        $this->getOriginal()->expects($this->never())->method('load');
        $this->call($productRoute, $request, $salesChannelContextMock);
    }

    public function testShopwareHandlesRequestWhenFindologicisInactive(): void
    {
        $salesChannelContextMock = $this->getMockedSalesChannelContext(false);
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $productRoute = $this->getRoute();

        $this->getOriginal()->expects($this->once())->method('load');
        $this->call($productRoute, $request, $salesChannelContextMock);
    }

    protected function call(
        $productRoute,
        Request $request,
        SalesChannelContext $salesChannelContext,
        string $categoryId = '69'
    ): void {
        if ($productRoute instanceof AbstractProductListingRoute) {
            $productRoute->load($categoryId, $request, $salesChannelContext);
        } elseif ($productRoute instanceof AbstractProductSearchRoute) {
            /** @var $productRoute AbstractProductSearchRoute */
            $productRoute->load($request, $salesChannelContext);
        } else {
            throw new InvalidArgumentException('Unknown productRoute of class %s', get_class($productRoute));
        }
    }
}
