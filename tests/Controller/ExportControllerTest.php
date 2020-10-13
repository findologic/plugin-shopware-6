<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Controller\ExportController;
use FINDOLOGIC\FinSearch\Exceptions\Export\UnknownShopkeyException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ExportHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prems\Plugin\PremsOnePageCheckout6\Core\OnePageCheckout\Storefront\ConfigService;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\Router;
use SimpleXMLElement;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;
    use ConfigHelper;
    use ExportHelper;

    /** @var Router $router */
    private $router;

    /** @var ExportController */
    private $exportController;

    /** @var Logger|MockObject */
    private $loggerMock;

    /** @var Context */
    private $defaultContext;

    /** @var string */
    private $validShopkey = '80AB18D4BE2654E78244106AD315DC2C';

    /** @var Request */
    private $request;

    /** @var EntityRepository|MockObject */
    private $systemConfigRepositoryMock;

    /** @var SalesChannelContext|MockObject */
    private $salesChannelContextMock;

    /** @var EntityRepository|MockObject */
    private $productRepositoryMock;

    /** @var ConfigService|MockObject */
    private $configServiceMock;

    /** @var PsrContainerInterface|MockObject */
    private $containerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = $this->getContainer()->get('router');
        $this->loggerMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->exportController = new ExportController(
            $this->loggerMock,
            $this->router,
            $this->getContainer()->get(HeaderHandler::class),
            $this->getContainer()->get(SalesChannelContextFactory::class)
        );
        $this->defaultContext = Context::createDefaultContext();

        $this->salesChannelContextMock = $this->getDefaultSalesChannelContextMock();
        $this->systemConfigRepositoryMock = $this->getSystemConfigRepositoryMock();
        $this->configServiceMock = $this->getDefaultFindologicConfigServiceMock($this);
    }

    public function invalidArgumentProvider(): array
    {
        return [
            'No shopkey was provided' => [
                'shopkey' => '',
                'start' => 1,
                'count' => 20,
                'exceptionMessage' => 'This value should not be blank.'
            ],
            'Malformed shopkey provided' => [
                'shopkey' => 'ABCD01815',
                'start' => 1,
                'count' => 20,
                'exceptionMessage' => 'This value is not valid.'
            ],
            '"count" parameter is zero' => [
                'shopkey' => $this->validShopkey,
                'start' => 1,
                'count' => 0,
                'exceptionMessage' => 'This value should be greater than 0.'
            ],
            '"count" parameter is a negative number' => [
                'shopkey' => $this->validShopkey,
                'start' => 1,
                'count' => -1,
                'exceptionMessage' => 'This value should be greater than 0.'
            ],
            '"start" parameter is a negative number' => [
                'shopkey' => $this->validShopkey,
                'start' => -1,
                'count' => 20,
                'exceptionMessage' => 'This value should be greater than or equal to 0.'
            ],
        ];
    }

    /**
     * @dataProvider invalidArgumentProvider
     *
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    public function testExportWithInvalidArguments(
        string $shopkey,
        $start,
        $count,
        string $exceptionMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getDefaultSalesChannelContextMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $shopkey, 'start' => $start, 'count' => $count]);

        $this->exportController->export($request, $salesChannelContextMock);
    }

    public function validArgumentProvider(): array
    {
        return [
            'Well formed shopkey provided' => [
                'start' => 1,
                'count' => 20
            ],
            '"start" parameter is zero with well formed shopkey' => [
                'start' => 0,
                'count' => 20
            ],
        ];
    }

    /**
     * @dataProvider validArgumentProvider
     *
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    public function testExportWithValidArguments(
        int $start,
        int $count
    ): void {
        $expectedProductCount = 1;
        $ids = $this->generateProductsAndSetUpMocks($expectedProductCount, $start, $count);

        $result = $this->doExport();
        $this->assertEquals(200, $result->getStatusCode());
        $xml = new SimpleXMLElement($result->getContent());
        $this->assertSame($expectedProductCount, (int)$xml->items[0]->attributes()['count']);
        $this->assertSame(reset($ids), (string)$xml->items->item['id']);
    }

    public function crossSellingCategoryProvider(): array
    {
        $categoryOne = Uuid::randomHex();
        $categoryTwo = Uuid::randomHex();
        $notInCrossSellingCategory = Uuid::randomHex();
        $navigationCategoryId = $this->getNavigationCategoryId();

        return [
            'No cross-sell categories configured' => [
                'assignedCategory' => [
                    [
                        'parentId' => $navigationCategoryId,
                        'id' => $notInCrossSellingCategory,
                        'name' => 'NotInCrossSellingCategory'
                    ]
                ],
                'crossSellingCategories' => [],
                'expectedCount' => 1
            ],
            'Article does not exist in cross-sell category configured' => [
                'assignedCategory' => [
                    [
                        'parentId' => $navigationCategoryId,
                        'id' => $notInCrossSellingCategory,
                        'name' => 'NotInCrossSellingCategory'
                    ]
                ],
                'crossSellingCategories' => [$categoryOne],
                'expectedCount' => 1
            ],
            'Article exists in one of the cross-sell categories configured' => [
                'assignedCategory' => [
                    [
                        'parentId' => $navigationCategoryId,
                        'id' => $categoryOne,
                        'name' => 'Category 1'
                    ]
                ],
                'crossSellingCategories' => [$categoryOne, $categoryTwo],
                'expectedCount' => 0
            ],
            'Article exists in all of cross-sell categories configured' => [
                'assignedCategory' => [
                    [
                        'parentId' => $navigationCategoryId,
                        'id' => $categoryOne,
                        'name' => 'Category 1',
                    ],
                    [
                        'parentId' => $navigationCategoryId,
                        'id' => $categoryTwo,
                        'name' => 'Someothercategory',
                    ]
                ],
                'crossSellingCategories' => [$categoryOne, $categoryTwo],
                'expectedCount' => 0
            ],
        ];
    }

    /**
     * @dataProvider crossSellingCategoryProvider
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    public function testExportWithCrossSellingCategories(
        array $assignedCategories,
        array $crossSellingCategories,
        int $expectedCount
    ): void {
        $salesChannelContextMock = $this->getDefaultSalesChannelContextMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $this->validShopkey]);

        /* @var ProductEntity $productEntity */
        $data['categories'] = $assignedCategories;

        $productEntity = $this->createTestProduct($data);
        $this->assertInstanceOf(ProductEntity::class, $productEntity);

        /** @var ProductCollection $productCollection */
        $productCollection = new ProductCollection([$productEntity]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parent.id', null));
        $criteria->addFilter(
            new ProductAvailableFilter(
                Defaults::SALES_CHANNEL,
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $criteria = Utils::addProductAssociations($criteria);
        $criteria->setOffset(0);
        $criteria->setLimit(20);

        /** @var EntitySearchResult $productEntitySearchResult */
        $productEntitySearchResult = new EntitySearchResult(
            1,
            $productCollection,
            null,
            $criteria,
            $this->defaultContext
        );

        /** @var EntityRepository|MockObject $productRepositoryMock */
        $productRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $productRepositoryMock->expects($this->once())
            ->method('search')
            ->with($criteria, $this->defaultContext)
            ->willReturn($productEntitySearchResult);

        $override['crossSellingCategories'] = $crossSellingCategories;
        $override['salesChannelId'] = Defaults::SALES_CHANNEL;
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock($this, $override);

        $services['product.repository'] = $productRepositoryMock;
        $services[SystemConfigService::class] = $configServiceMock;

        $containerMock = $this->getContainerMock($services);
        $this->exportController->setContainer($containerMock);

        $result = $this->exportController->export($request, $salesChannelContextMock);

        $this->assertEquals(200, $result->getStatusCode());
        $xml = new SimpleXMLElement($result->getContent());
        $this->assertSame($expectedCount, (int)$xml->items[0]->attributes()['count']);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    public function testExportWithSalesChannelId(): void
    {
        $expectedProductCount = 1;
        $ids = $this->generateProductsAndSetUpMocks($expectedProductCount);

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();

        $systemConfigEntity->expects($this->once())
            ->method('getConfigurationValue')
            ->willReturn($this->validShopkey);
        $systemConfigEntity->expects($this->any())
            ->method('getSalesChannelId')
            ->willReturn(Defaults::SALES_CHANNEL);
        $this->systemConfigRepositoryMock = $this->getSystemConfigRepositoryMock($systemConfigEntity);

        $this->containerMock->expects($this->any())->method('get')->willReturnMap([
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['sales_channel.repository', $this->getContainer()->get('sales_channel.repository')],
            ['currency.repository', $this->getContainer()->get('currency.repository')],
            ['customer.repository', $this->getContainer()->get('customer.repository')],
            ['country.repository', $this->getContainer()->get('country.repository')],
            ['tax.repository', $this->getContainer()->get('tax.repository')],
            ['translator', $this->getContainer()->get('translator')],
            ['customer_address.repository', $this->getContainer()->get('customer_address.repository')],
            ['payment_method.repository', $this->getContainer()->get('payment_method.repository')],
            ['shipping_method.repository', $this->getContainer()->get('shipping_method.repository')],
            ['country_state.repository', $this->getContainer()->get('country_state.repository')],
            ['order_line_item.repository', $this->getContainer()->get('order_line_item.repository')],
            [FindologicProductFactory::class, $this->getContainer()->get(FindologicProductFactory::class)],
            [SalesChannelContextFactory::class, $this->getContainer()->get(SalesChannelContextFactory::class)],
        ]);

        $result = $this->doExport();
        $this->assertEquals(200, $result->getStatusCode());
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    public function testExportWithUnknownShopkey(): void
    {
        $unknownShopkey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

        $this->expectException(UnknownShopkeyException::class);
        $this->expectExceptionMessage(sprintf('Given shopkey "%s" is not assigned to any shop', $unknownShopkey));

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->once())->method('getConfigurationValue')->willReturn($this->validShopkey);
        $systemConfigEntity->expects($this->never())->method('getSalesChannelId')->willReturn($this->validShopkey);

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getSystemConfigRepositoryMock($systemConfigEntity);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getDefaultSalesChannelContextMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $unknownShopkey]);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->once())->method('get')->willReturn($systemConfigRepositoryMock);

        $this->exportController->setContainer($containerMock);
        $this->exportController->export($request, $salesChannelContextMock);
    }

    /**
     * @return string[]
     */
    public function exportHeaderProvider(): array
    {
        $headers['content-type'] = ['text/xml'];
        $headers['x-findologic-platform'] = ['Shopware/6.1.3'];
        $headers['x-findologic-plugin'] = ['Plugin-Shopware-6/0.1.0'];
        $headers['x-findologic-extension-plugin'] = ['Plugin-Shopware-6-Extension/1.0.1'];

        return [
            'Correct headers values with correct versions are returned' => [
                'expectedHeaders' => $headers,
            ]
        ];
    }

    /**
     * @return string[]
     */
    public function exportHeaderWithoutExtensionPluginProvider(): array
    {
        $headers['content-type'] = ['text/xml'];
        $headers['x-findologic-platform'] = ['Shopware/6.1.3'];
        $headers['x-findologic-plugin'] = ['Plugin-Shopware-6/0.1.0'];
        $headers['x-findologic-extension-plugin'] = ['none'];

        return [
            'Correct headers values are returned when there is no extension plugin installed' => [
                'expectedHeaders' => $headers,
            ]
        ];
    }

    /**
     * @dataProvider exportHeaderProvider
     * @dataProvider exportHeaderWithoutExtensionPluginProvider
     *
     * @param string[] $expectedHeaders
     *
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    public function testExportHeaders(array $expectedHeaders): void
    {
        $salesChannelId = Defaults::SALES_CHANNEL;

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getDefaultSalesChannelContextMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $this->validShopkey, 'start' => 0, 'count' => 20]);

        /** @var PsrContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(PsrContainerInterface::class)
            ->disableOriginalConstructor()
            ->addMethods(['set'])
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $containerMock->expects($this->exactly(2))->method('set');

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getSystemConfigRepositoryMock();

        /** @var ProductEntity $productEntity */
        $productEntity = $this->createTestProduct();
        $this->assertInstanceOf(ProductEntity::class, $productEntity);

        /** @var ProductCollection $productCollection */
        $productCollection = new ProductCollection([$productEntity]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parent.id', null));
        $criteria->addFilter(
            new ProductAvailableFilter(
                $salesChannelId,
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $criteria = Utils::addProductAssociations($criteria);
        $criteria->setOffset(0);
        $criteria->setLimit(20);

        $productIdSearchResult = new IdSearchResult(
            13,
            [],
            $criteria,
            $this->defaultContext
        );

        /** @var EntitySearchResult $productEntitySearchResult */
        $productEntitySearchResult = new EntitySearchResult(
            1,
            $productCollection,
            null,
            $criteria,
            $this->defaultContext
        );

        $criteriaWithoutOffsetLimit = clone $criteria;
        $criteriaWithoutOffsetLimit->setOffset(null);
        $criteriaWithoutOffsetLimit->setLimit(null);

        /** @var EntityRepository|MockObject $productRepositoryMock */
        $productRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $productRepositoryMock->expects($this->any())
            ->method('searchIds')
            ->with($criteriaWithoutOffsetLimit, $this->defaultContext)
            ->willReturn($productIdSearchResult);
        $productRepositoryMock->expects($this->any())
            ->method('search')
            ->with($criteria, $this->defaultContext)
            ->willReturn($productEntitySearchResult);

        $pluginCriteria = new Criteria();
        $pluginCriteria->addFilter(new EqualsFilter('name', 'FinSearch'));

        $extensionCriteria = new Criteria();
        $extensionCriteria->addFilter(new EqualsFilter('name', 'ExtendFinSearch'));

        $pluginEntity = $this->getMockBuilder(PluginEntity::class)->disableOriginalConstructor()->getMock();
        $pluginEntity->method('getVersion')->willReturn('0.1.0');

        $pluginCollection = new PluginCollection([$pluginEntity]);

        $pluginEntitySearchResult = new EntitySearchResult(
            1,
            $pluginCollection,
            null,
            $pluginCriteria,
            $this->defaultContext
        );

        $extensionPluginEntity = $this->getMockBuilder(PluginEntity::class)->disableOriginalConstructor()->getMock();
        $extensionPluginEntity->method('getVersion')->willReturn('1.0.1');

        $extensionPluginCollection = new PluginCollection([$extensionPluginEntity]);

        $extensionPluginEntitySearchResult = new EntitySearchResult(
            1,
            $extensionPluginCollection,
            null,
            $extensionCriteria,
            $this->defaultContext
        );

        $pluginRepositoryMock = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();
        $pluginRepositoryMock->expects($this->at(0))
            ->method('search')
            ->with($pluginCriteria, $this->defaultContext)
            ->willReturn($pluginEntitySearchResult);
        $pluginRepositoryMock->expects($this->at(1))
            ->method('search')
            ->with($extensionCriteria, $this->defaultContext)
            ->willReturn($extensionPluginEntitySearchResult);

        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock($this);

        $containerRepositoriesMap = [
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['order_line_item.repository', $this->getContainer()->get('order_line_item.repository')],
            ['product.repository', $productRepositoryMock],
            ['translator', $this->getContainer()->get('translator')],
            ['fin_search.sales_channel_context', $salesChannelContextMock],
            [SystemConfigService::class, $configServiceMock],
            [FindologicProductFactory::class, new FindologicProductFactory()],
            [SalesChannelService::class, new SalesChannelService($systemConfigRepositoryMock, $this->getContainer()->get(SalesChannelContextFactory::class))]
        ];
        $containerMock->method('get')->willReturnMap($containerRepositoriesMap);

        /** @var Container|MockObject $symfonyContainerMock */
        $symfonyContainerMock = $this->getMockBuilder(Container::class)->disableOriginalConstructor()->getMock();
        $symfonyContainerMock->expects($this->once())->method('getParameter')
            ->with('kernel.shopware_version')
            ->willReturn('6.1.3');
        $symfonyContainerMock->expects($this->once())->method('get')
            ->with('plugin.repository')
            ->willReturn($pluginRepositoryMock);

        /** @var HeaderHandler|MockObject $headerHandler */
        $headerHandler = $this->getMockBuilder(HeaderHandler::class)
            ->setConstructorArgs([$symfonyContainerMock])
            ->getMock();

        $headerHandler->expects($this->once())->method('getHeaders')->willReturn($expectedHeaders);

        $this->exportController = new ExportController(
            $this->loggerMock,
            $this->router,
            $headerHandler,
            $this->getContainer()->get(SalesChannelContextFactory::class)
        );
        $this->exportController->setContainer($containerMock);
        $result = $this->exportController->export($request, $salesChannelContextMock);

        $headers = $result->headers->all();

        // Remove unwanted headers for testing only implemented header values
        unset($headers['cache-control'], $headers['date']);

        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testExportReturnsOnlyProductWhenProductIdIsProvided(): void
    {
        $salesChannelId = Defaults::SALES_CHANNEL;

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);

        $salesChannelContextMock->expects($this->exactly(6))
            ->method('getContext')
            ->willReturn($this->defaultContext);

        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelMock);

        /** @var ProductEntity $productEntity */
        $productEntity = $this->createTestProduct();
        $this->assertInstanceOf(ProductEntity::class, $productEntity);

        /** @var Request $request */
        $request = new Request(['shopkey' => $this->validShopkey, 'productId' => $productEntity->getId()]);

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->once())->method('getConfigurationValue')->willReturn($this->validShopkey);
        $systemConfigEntity->expects($this->once())->method('getSalesChannelId')->willReturn(null);

        /** @var SystemConfigCollection $systemConfigCollection */
        $systemConfigCollection = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $systemConfigEntitySearchResult */
        $systemConfigEntitySearchResult = new EntitySearchResult(
            1,
            $systemConfigCollection,
            null,
            new Criteria(),
            $this->defaultContext
        );

        $systemConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($systemConfigEntitySearchResult);

        /** @var ProductCollection $productCollection */
        $productCollection = new ProductCollection([$productEntity]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parent.id', null));
        $criteria->addFilter(
            new ProductAvailableFilter(
                $salesChannelId,
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $criteria = Utils::addProductAssociations($criteria);

        $criteriaWithoutOffsetLimit = clone $criteria;

        $criteria->setOffset(0);
        $criteria->setLimit(20);
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('ean', $productEntity->getId()),
                new EqualsFilter('manufacturerNumber', $productEntity->getId()),
                new EqualsFilter('productNumber', $productEntity->getId()),
                new EqualsFilter('id', $productEntity->getId()),
            ]
        ));

        $productIdSearchResult = new IdSearchResult(
            1,
            [],
            $criteria,
            $this->defaultContext
        );

        /** @var EntitySearchResult $productEntitySearchResult */
        $productEntitySearchResult = new EntitySearchResult(
            1,
            $productCollection,
            null,
            $criteria,
            $this->defaultContext
        );

        /** @var EntityRepository|MockObject $productRepositoryMock */
        $productRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $productRepositoryMock->expects($this->any())
            ->method('searchIds')
            ->with($criteriaWithoutOffsetLimit, $this->defaultContext)
            ->willReturn($productIdSearchResult);
        $productRepositoryMock->expects($this->any())
            ->method('search')
            ->with($criteria, $this->defaultContext)
            ->willReturn($productEntitySearchResult);

        $containerMock = $this->getContainerMock([
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['order_line_item.repository', $this->getContainer()->get('order_line_item.repository')],
            ['product.repository', $productRepositoryMock],
            ['translator', $this->getContainer()->get('translator')],
            [FindologicProductFactory::class, new FindologicProductFactory()],
            ['fin_search.sales_channel_context', $salesChannelContextMock],
        ]);

        /** @var HeaderHandler|MockObject $headerHandler */
        $headerHandler = $this->getMockBuilder(HeaderHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $headerHandler->expects($this->once())->method('getHeaders')->willReturn([]);

        $this->exportController = new ExportController(
            $this->loggerMock,
            $this->router,
            $headerHandler,
            $this->getContainer()->get(SalesChannelContextFactory::class)
        );
        $this->exportController->setContainer($containerMock);
        $result = $this->exportController->export($request, $salesChannelContextMock);

        $export = new SimpleXMLElement($result->getContent());

        $this->assertSame(0, (int)$export->items->attributes()->start);
        $this->assertSame(1, (int)$export->items->attributes()->count);
        $this->assertSame(1, (int)$export->items->attributes()->total);

        $this->assertSame($productEntity->getId(), (string)$export->items->item->attributes()->id);
    }

    private function getContainerMock(array $services = []): PsrContainerInterface
    {
        /** @var PsrContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(PsrContainerInterface::class)
            ->disableOriginalConstructor()
            ->addMethods(['set'])
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $containerMock->expects($this->exactly(2))->method('set');
        $containerMock->method('get')->willReturnMap($services);

        return $containerMock;
    }

    /**
     * @return string[] Generated product ids.
     */
    private function generateProductsAndSetUpMocks(
        int $productCount = 1,
        int $start = 0,
        int $count = 20,
        ?string $productId = null
    ): array {
        $products = [];
        for ($i = 0; $i < $productCount; $i++) {
            $products[] = $this->createTestProduct();
        }

        $params = ['start' => $start, 'count' => $count];
        if ($productId) {
            $params = array_merge($params, ['productId' => $productId]);
        }

        $collection = new ProductCollection($products);
        $this->productRepositoryMock = $this->getProductEntityRepositoryMock($collection, $start, $count);
        $this->request = $this->buildExportRequest($params);
        $this->containerMock = $this->setUpContainerMock();

        return $collection->getIds();
    }

    /**
     * @return EntityRepository|MockObject
     */
    private function getProductEntityRepositoryMock(
        ProductCollection $products,
        int $start,
        int $count
    ): EntityRepository {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parent.id', null));
        $criteria->addFilter(
            new ProductAvailableFilter(
                Defaults::SALES_CHANNEL,
                ProductVisibilityDefinition::VISIBILITY_SEARCH
            )
        );

        $criteria = Utils::addProductAssociations($criteria);
        $criteria->setOffset($start);
        $criteria->setLimit($count);

        $productEntitySearchResult = new EntitySearchResult(
            $products->count(),
            $products,
            null,
            $criteria,
            $this->defaultContext
        );

        $productIdSearchResult = new IdSearchResult(
            $products->count(),
            [],
            $criteria,
            $this->defaultContext
        );

        $criteriaWithoutOffsetLimit = clone $criteria;
        $criteriaWithoutOffsetLimit->setOffset(null);
        $criteriaWithoutOffsetLimit->setLimit(null);

        /** @var EntityRepository|MockObject $productRepositoryMock */
        $productRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $productRepositoryMock->expects($this->any())
            ->method('searchIds')
            ->with($criteriaWithoutOffsetLimit, $this->defaultContext)
            ->willReturn($productIdSearchResult);
        $productRepositoryMock->expects($this->any())
            ->method('search')
            ->with($criteria, $this->defaultContext)
            ->willReturn($productEntitySearchResult);

        return $productRepositoryMock;
    }

    private function buildExportRequest(array $queryParamsOverrides): Request
    {
        $queryParams = [
            'shopkey' => $this->validShopkey
        ];

        return new Request(array_merge($queryParams, $queryParamsOverrides));
    }

    /**
     * @return PsrContainerInterface|MockObject
     */
    protected function setUpContainerMock(): PsrContainerInterface
    {
        $containerRepositoriesMap = [
            ['system_config.repository', $this->systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['order_line_item.repository', $this->getContainer()->get('order_line_item.repository')],
            ['translator', $this->getContainer()->get('translator')],
            ['product.repository', $this->productRepositoryMock],
            ['fin_search.sales_channel_context', $this->salesChannelContextMock],
            [SystemConfigService::class, $this->configServiceMock],
            [FindologicProductFactory::class, new FindologicProductFactory()],
            [SalesChannelService::class, new SalesChannelService($this->systemConfigRepositoryMock, $this->getContainer()->get(SalesChannelContextFactory::class))],
        ];

        return $this->getContainerMock($containerRepositoriesMap);
    }

    /**
     * @return Response
     * @throws UnknownShopkeyException
     */
    protected function doExport(): Response
    {
        $this->exportController->setContainer($this->containerMock);

        return $this->exportController->export($this->request, $this->salesChannelContextMock);
    }
}
