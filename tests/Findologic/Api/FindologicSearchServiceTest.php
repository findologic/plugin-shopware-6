<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Api;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use FINDOLOGIC\FinSearch\Findologic\Api\PaginationService;
use FINDOLOGIC\FinSearch\Findologic\Api\SortingService;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SortingHandlerService;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config as PluginConfig;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Struct\SystemAware;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FindologicSearchServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;

    private ApiClient|MockObject $apiClientMock;

    private ApiConfig $apiConfig;

    private PluginConfig|MockObject $pluginConfigMock;

    private ServiceConfigResource|MockObject $serviceConfigResourceMock;

    protected function setUp(): void
    {
        $this->apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->apiConfig = new ApiConfig('ABCDABCDABCDABCDABCDABCDABCDABCD');
        $this->pluginConfigMock = $this->getMockBuilder(PluginConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->serviceConfigResourceMock = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public static function stagingQueryParameterProvider(): array
    {
        return [
            'Shop is not staging and no query parameter was submitted' => [
                'isStaging' => false,
                'stagingFlag' => false,
                'stagingParam' => null,
                'isFindologicEnabled' => true
            ],
            'Shop is not staging and query parameter is findologic=off' => [
                'isStaging' => false,
                'stagingFlag' => false,
                'stagingParam' => 'off',
                'isFindologicEnabled' => true
            ],
            'Shop is not staging and query parameter is findologic=disabled' => [
                'isStaging' => false,
                'stagingFlag' => false,
                'stagingParam' => 'disabled',
                'isFindologicEnabled' => true
            ],
            'Shop is not staging and query parameter is findologic=on' => [
                'isStaging' => false,
                'stagingFlag' => true,
                'stagingParam' => 'on',
                'isFindologicEnabled' => true
            ],
            'Shop is staging and no query parameter was submitted' => [
                'isStaging' => true,
                'stagingFlag' => false,
                'stagingParam' => null,
                'isFindologicEnabled' => false
            ],
            'Shop is staging and query parameter is findologic=off' => [
                'isStaging' => true,
                'stagingFlag' => false,
                'stagingParam' => 'off',
                'isFindologicEnabled' => false
            ],
            'Shop is staging and query parameter is findologic=disabled' => [
                'isStaging' => true,
                'stagingFlag' => false,
                'stagingParam' => 'disabled',
                'isFindologicEnabled' => false
            ]
        ];
    }

    /**
     * @dataProvider stagingQueryParameterProvider
     */
    public function testStagingQueryParameterWorksAsExpected(
        bool $isStaging,
        bool $stagingFlag,
        ?string $stagingParam,
        bool $isFindologicEnabled
    ): void {
        $this->pluginConfigMock->expects($this->any())->method('isActive')->willReturn($isFindologicEnabled);
        $this->pluginConfigMock->expects($this->any())->method('getShopkey')
            ->willReturn('ABCDABCDABCDABCDABCDABCDABCDABCD');
        $this->pluginConfigMock->expects($this->any())->method('isInitialized')->willReturn(true);
        $this->serviceConfigResourceMock->expects($this->any())->method('isStaging')->willReturn($isStaging);
        $this->serviceConfigResourceMock->expects($this->any())->method('isDirectIntegration')->willReturn(false);

        $sessionMock = $this->getMockBuilder(SessionInterface::class)->disableOriginalConstructor()->getMock();

        if ($stagingParam === null) {
            $sessionMock->expects($this->once())->method('get')->with('stagingFlag')->willReturn($stagingFlag);
            $invokeCount = $this->never();
        } else {
            $invokeCount = $this->once();
        }

        $sessionMock->expects($invokeCount)->method('set')->with('stagingFlag', $stagingFlag);

        $request = new Request();
        $request->query->set('findologic', $stagingParam);
        $request->setSession($sessionMock);

        $event = new ProductSearchCriteriaEvent($request, new Criteria(), $this->buildAndCreateSalesChannelContext());

        $serviceConfigResourceMock = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $serviceConfigResourceMock->method('isDirectIntegration')->willReturn(false);

        $containerMock = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->expects($this->any())->method('get')
            ->willReturnCallback(function ($serviceName) use ($serviceConfigResourceMock) {
                if ($serviceName === ServiceConfigResource::class) {
                    return $serviceConfigResourceMock;
                }

                return $this->getContainer()->get($serviceName);
            });

        $findologicSearchService = new FindologicSearchService(
            $this->apiClientMock,
            $this->apiConfig,
            $this->pluginConfigMock,
            $this->getContainer()->get(SortingService::class),
            $this->getContainer()->get(PaginationService::class),
            $this->getContainer()->get(SortingHandlerService::class),
            $this->serviceConfigResourceMock,
            $this->getContainer()->get(SearchRequestFactory::class),
            $this->getContainer()->get(NavigationRequestFactory::class),
            $this->getContainer()->get(SystemAware::class),
            $this->getContainer()->get('category.repository'),
        );

        $reflector = new ReflectionObject($findologicSearchService);
        $method = $reflector->getMethod('allowRequest');
        $isEnabled = $method->invoke($findologicSearchService, $event);
        $this->assertSame($isFindologicEnabled, $isEnabled);
    }

    public function testAllowedRequestButUnknownCategoryDisablesFindologic(): void
    {
        $findologicSearchService = new FindologicSearchService(
            $this->apiClientMock,
            $this->apiConfig,
            $this->pluginConfigMock,
            $this->getContainer()->get(SortingService::class),
            $this->getContainer()->get(PaginationService::class),
            $this->getContainer()->get(SortingHandlerService::class),
            $this->serviceConfigResourceMock,
            $this->getContainer()->get(SearchRequestFactory::class),
            $this->getContainer()->get(NavigationRequestFactory::class),
            $this->getContainer()->get(SystemAware::class),
            $this->getContainer()->get('category.repository'),
        );

        $this->pluginConfigMock->expects($this->any())->method('isInitialized')->willReturn(true);

        $findologicService = new FindologicService();
        $findologicService->enable();
        $findologicService->enableSmartSuggest();

        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contextMock->expects($this->any())->method('getExtension')->willReturnCallback(
            function (string $name) use ($findologicService) {
                if ($name === 'findologicService') {
                    return $findologicService;
                }

                return null;
            }
        );

        // The root category is considered unknown, as this is a category, which is not directly
        // indexed by Findologic.
        $request = new Request(['navigationId' => $this->getRootCategory()->getId()]);
        $salesChannelContext = $this->buildAndCreateSalesChannelContext();
        $salesChannelContext->getContext()->addExtension('findologicService', $findologicService);

        $event = new ProductSearchCriteriaEvent($request, new Criteria(), $salesChannelContext);

        $findologicSearchService->doNavigation($event);

        $this->assertFalse($findologicService->getEnabled());
        $this->assertTrue($findologicService->getSmartSuggestEnabled()); // State of SS shouldn't be changed.
    }

    private function getRootCategory(): CategoryEntity
    {
        $categoryRepo = $this->getContainer()->get('category.repository');
        $categories = $categoryRepo->search(new Criteria(), Context::createDefaultContext());

        /** @var CategoryEntity $expectedCategory */
        return $categories->filter(function (CategoryEntity $category) {
            return $category->getParentId() === null;
        })->first();
    }
}
