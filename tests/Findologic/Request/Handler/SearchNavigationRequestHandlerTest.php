<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchNavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\MockResponseHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

class SearchNavigationRequestHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use MockResponseHelper;

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    /** @var Config|MockObject */
    private $configMock;

    /** @var ApiConfig */
    private $apiConfig;

    /** @var ApiClient */
    private $apiClientMock;

    /** @var FindologicRequestFactory|MockObject */
    private $findologicRequestFactoryMock;

    /** @var GenericPageLoader|MockObject */
    private $genericPageLoaderMock;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->apiConfig = new ApiConfig(self::VALID_SHOPKEY);
        $this->apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->findologicRequestFactoryMock = $this->getMockBuilder(FindologicRequestFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->genericPageLoaderMock = $this->getMockBuilder(GenericPageLoader::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testAddsUserGroupHashForSearch(): void
    {
        $customer = $this->createAndGetCustomer();
        $this->salesChannelContext = $this->buildSalesChannelContext(
            Defaults::SALES_CHANNEL,
            'http://test.at',
            $customer
        );
        $event = $this->buildSearchEvent($this->salesChannelContext);

        $expectedUserGroup = Utils::calculateUserGroupHash(
            self::VALID_SHOPKEY,
            $this->salesChannelContext->getCustomer()->getGroupId()
        );

        $searchRequest = new SearchRequest();
        $this->findologicRequestFactoryMock->expects($this->exactly(2))
            ->method('getInstance')
            ->willReturn($searchRequest);

        $this->apiClientMock->expects($this->once())
            ->method('send')
            ->willReturn(new Xml21Response($this->getMockResponse()));

        $requestHandler = $this->buildSearchRequestHandler();
        $requestHandler->handleRequest($event);

        $this->assertSame($expectedUserGroup, $searchRequest->getParams()['usergrouphash'][0]);
    }

    public function testAddsUserGroupHashForNavigation(): void
    {
        $customer = $this->createAndGetCustomer();
        $this->salesChannelContext = $this->buildSalesChannelContext(
            Defaults::SALES_CHANNEL,
            'http://test.at',
            $customer
        );

        // Create a product, which will create some categories, which are assigned to it.
        $this->createTestProduct();
        $oneSubCategoryFilter = new Criteria();
        $oneSubCategoryFilter->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [
            new EqualsFilter('parentId', null),
        ]))->setLimit(1);

        $categoryRepo = $this->getContainer()->get('category.repository');
        $category = $categoryRepo->search($oneSubCategoryFilter, Context::createDefaultContext())->first();

        $event = $this->buildNavigationEvent(
            $this->salesChannelContext,
            new Request(['navigationId' => $category->getId()])
        );

        $expectedUserGroup = Utils::calculateUserGroupHash(
            self::VALID_SHOPKEY,
            $this->salesChannelContext->getCustomer()->getGroupId()
        );

        $navigationRequest = new NavigationRequest();
        $this->findologicRequestFactoryMock->expects($this->any())
            ->method('getInstance')
            ->willReturn($navigationRequest);

        $this->apiClientMock->expects($this->once())
            ->method('send')
            ->willReturn(new Xml21Response($this->getMockResponse()));

        $pageMock = $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->genericPageLoaderMock->expects($this->once())->method('load')->willReturn($pageMock);

        $requestHandler = $this->buildNavigationRequestHandler();
        $requestHandler->handleRequest($event);

        $this->assertSame($expectedUserGroup, $navigationRequest->getParams()['usergrouphash'][0]);
    }

    private function buildSearchRequestHandler(): SearchRequestHandler
    {
        return new SearchRequestHandler(
            $this->getContainer()->get(ServiceConfigResource::class),
            $this->findologicRequestFactoryMock,
            $this->configMock,
            $this->apiConfig,
            $this->apiClientMock
        );
    }

    private function buildNavigationRequestHandler(): NavigationRequestHandler
    {
        return new NavigationRequestHandler(
            $this->getContainer()->get(ServiceConfigResource::class),
            $this->findologicRequestFactoryMock,
            $this->configMock,
            $this->apiConfig,
            $this->apiClientMock,
            $this->genericPageLoaderMock,
            $this->getContainer()
        );
    }

    private function createAndGetCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customerRepo = $this->getContainer()->get('customer.repository');

        return $customerRepo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

    private function buildSearchEvent(
        ?SalesChannelContext $salesChannelContext = null,
        ?Request $request = null,
        ?Criteria $criteria = null
    ): ProductSearchCriteriaEvent {
        $salesChannelContext = $salesChannelContext ?? $this->buildSalesChannelContext();
        $request = $request ?? new Request();
        $criteria = $criteria ?? new Criteria();

        return new ProductSearchCriteriaEvent($request, $criteria, $salesChannelContext);
    }

    private function buildNavigationEvent(
        ?SalesChannelContext $salesChannelContext = null,
        ?Request $request = null,
        ?Criteria $criteria = null
    ): ProductListingCriteriaEvent {
        $salesChannelContext = $salesChannelContext ?? $this->buildSalesChannelContext();
        $request = $request ?? new Request();
        $criteria = $criteria ?? new Criteria();

        return new ProductListingCriteriaEvent($request, $criteria, $salesChannelContext);
    }
}
