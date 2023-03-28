<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\CategoryHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

abstract class ProductRouteBase extends TestCase
{
    use CategoryHelper;
    use IntegrationTestBehaviour;

    /**
     * @var EventDispatcherInterface|MockObject
     */
    protected $eventDispatcherMock;

    protected ProductDefinition $productDefinition;

    protected RequestCriteriaBuilder $criteriaBuilder;

    /**
     * @var SalesChannelRepository|MockObject
     */
    protected $productRepositoryMock;

    /**
     * @var EntityRepository|MockObject
     */
    protected $categoryRepositoryMock;

    /**
     * @var ProductStreamBuilderInterface|MockObject
     */
    protected $productStreamBuilderMock;

    /**
     * @var ProductSearchBuilderInterface|MockObject
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

    /**
     * @var FindologicConfigService|MockObject
     */
    protected $findologicConfigServiceMock;

    protected function setUp(): void
    {
        $this->eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $this->criteriaBuilder = $this->getContainer()->get(RequestCriteriaBuilder::class);

        $this->productRepositoryMock = $this->getMockBuilder(SalesChannelRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->categoryRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->productStreamBuilderMock = $this->getMockBuilder(ProductStreamBuilderInterface::class)
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

        $this->findologicConfigServiceMock = $this->getMockBuilder(FindologicConfigService::class)
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
        $salesChannelMock->expects($this->any())->method('getId')->willReturn(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $salesChannelContextMock->expects($this->any())
            ->method('getSalesChannel')
            ->willReturn($salesChannelMock);
        $salesChannelContextMock->expects($this->any())
            ->method('getSalesChannelId')
            ->willReturn(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        /** @var Context|MockObject $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $context->method('getVersionId')->willReturn(Defaults::LIVE_VERSION);
        $context->expects($this->any())
            ->method('addState')
            ->with(Criteria::STATE_ELASTICSEARCH_AWARE);

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

    protected function setCategoryMock(
        ?string $categoryId = null,
        ?string $productAssignmentType = null,
        ?string $streamId = null
    ) {
        $category = $this->createTestCategory([
            'id' => $categoryId ?? Uuid::randomHex()
        ]);

        $supportsProductStreams = defined(
            '\Shopware\Core\Content\Category\CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM'
        );
        if ($supportsProductStreams) {
            $productStream = new ProductStreamEntity();
            $productStream->setId($streamId ?? Uuid::randomHex());
            $productAssignmentType ??= CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT;

            $category->setProductAssignmentType($productAssignmentType);
            $category->setProductStream($productStream);
            $category->setProductStreamId($productStream->getId());
        }

        $categoryResult = Utils::buildEntitySearchResult(
            CategoryEntity::class,
            1,
            new EntityCollection([$category]),
            null,
            new Criteria(),
            $this->getMockedSalesChannelContext(true)->getContext()
        );

        $this->categoryRepositoryMock->expects($this->any())->method('search')->willReturn($categoryResult);
    }

    public function testFindologicHandlesRequestWhenActive(): void
    {
        $salesChannelContextMock = $this->getMockedSalesChannelContext(true);
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $this->setCategoryMock();

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

    public function testCustomCriteriaIsAllowed(): void
    {
        $salesChannelContextMock = $this->getMockedSalesChannelContext(true);
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $expectedFilter = new EqualsFilter('product.name', 'yeet');
        $criteria = new Criteria();
        $criteria->addFilter($expectedFilter);

        $categoryId = Uuid::randomHex();
        $this->setCategoryMock($categoryId);

        $productRoute = $this->getRoute();
        $response = $this->call($productRoute, $request, $salesChannelContextMock, $categoryId, $criteria);

        switch (true) {
            case $response instanceof ProductSearchRouteResponse:
                $this->assertSame($expectedFilter, $response->getListingResult()->getCriteria()->getFilters()[0]);
                break;
            case $response instanceof ProductListingRouteResponse:
                $this->assertSame($expectedFilter, $response->getResult()->getCriteria()->getFilters()[0]);
                break;
            default:
                $this->fail(sprintf('Unknown route response %s', get_class($response)));
        }
    }

    public function testVariantAssociationsAreAdded(): void
    {
        $salesChannelContextMock = $this->getMockedSalesChannelContext(true);
        $request = Request::create('http://your-shop.de/some-category');
        $request->setSession($this->getSessionMock());

        $categoryId = Uuid::randomHex();
        $this->setCategoryMock($categoryId);

        $criteria = new Criteria();
        $productRoute = $this->getRoute();
        $this->call($productRoute, $request, $salesChannelContextMock, $categoryId, $criteria);

        $this->assertArrayHasKey('options', $criteria->getAssociations());
        $this->assertArrayHasKey('group', $criteria->getAssociations()['options']->getAssociations());
    }

    protected function call(
        $productRoute,
        Request $request,
        SalesChannelContext $salesChannelContext,
        string $categoryId = '69',
        ?Criteria $criteria = null
    ): StoreApiResponse {
        switch (true) {
            case $productRoute instanceof AbstractProductListingRoute:
                return $productRoute->load($categoryId, $request, $salesChannelContext, $criteria);
            case $productRoute instanceof AbstractProductSearchRoute:
                return $productRoute->load($request, $salesChannelContext, $criteria);
            default:
                throw new InvalidArgumentException('Unknown productRoute of class %s', get_class($productRoute));
        }
    }
}
