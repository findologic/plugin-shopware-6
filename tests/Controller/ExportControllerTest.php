<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Controller\ExportController;
use FINDOLOGIC\FinSearch\Exceptions\UnknownShopkeyException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Export\HeaderHandler;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ExportHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\Router;
use SimpleXMLElement;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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

    /**
     * @var EventDispatcherInterface|MockObject
     */
    private $eventDispatcherMock;

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
    }

    public function invalidArgumentProvider(): array
    {
        return [
            'No shopkey was provided' => [
                'shopkey' => '',
                'start' => 1,
                'count' => 20,
                'exceptionMessage' => sprintf(
                    'Required argument "shopkey" was not given, or does not match the shopkey schema "%s"',
                    ''
                )
            ],
            'Malformed shopkey provided' => [
                'shopkey' => 'ABCD01815',
                'start' => 1,
                'count' => 20,
                'exceptionMessage' => sprintf(
                    'Required argument "shopkey" was not given, or does not match the shopkey schema "%s"',
                    'ABCD01815'
                )
            ],
            '"count" parameter is zero' => [
                'shopkey' => $this->validShopkey,
                'start' => 1,
                'count' => 0,
                'exceptionMessage' => 'The value 0 is not greater than zero'
            ],
            '"count" parameter is some string' => [
                'shopkey' => $this->validShopkey,
                'start' => 'some string',
                'count' => 20,
                'exceptionMessage' => 'The value "some string" is not a valid numeric'
            ],
            '"count" parameter is a negative number' => [
                'shopkey' => $this->validShopkey,
                'start' => 1,
                'count' => -1,
                'exceptionMessage' => 'The value -1 is not greater than zero'
            ],
            '"start" parameter is some string' => [
                'shopkey' => $this->validShopkey,
                'start' => 'some string',
                'count' => 20,
                'exceptionMessage' => 'The value "some string" is not a valid numeric'
            ],
            '"start" parameter is a negative number' => [
                'shopkey' => $this->validShopkey,
                'start' => -1,
                'count' => 20,
                'exceptionMessage' => 'The value -1 is not greater than or equal to zero'
            ],
        ];
    }

    /**
     * @dataProvider invalidArgumentProvider
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
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

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
     * @throws InconsistentCriteriaIdsException
     * @throws UnknownShopkeyException
     */
    public function testExportWithValidArguments(
        int $start,
        int $count
    ): void {
        $salesChannelId = Defaults::SALES_CHANNEL;

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);

        $salesChannelContextMock->expects($this->exactly(5))
            ->method('getContext')
            ->willReturn($this->defaultContext);

        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelMock);

        /** @var Request $request */
        $request = new Request(['shopkey' => $this->validShopkey, 'start' => $start, 'count' => $count]);

        /** @var PsrContainerInterface|MockObject $containerMock */
        $containerMock
            = $this->getMockBuilder(PsrContainerInterface::class)->disableOriginalConstructor()->getMock();

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
        $criteria->setOffset($start);
        $criteria->setLimit($count);

        /** @var EntitySearchResult $productEntitySearchResult */
        $productEntitySearchResult = new EntitySearchResult(
            1,
            $productCollection,
            null,
            $criteria,
            $this->defaultContext
        );

        $productIdSearchResult = new IdSearchResult(
            13,
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

        $productRepositoryMock->expects($this->at(0))
            ->method('searchIds')
            ->with($criteriaWithoutOffsetLimit, $this->defaultContext)
            ->willReturn($productIdSearchResult);
        $productRepositoryMock->expects($this->at(1))
            ->method('search')
            ->with($criteria, $this->defaultContext)
            ->willReturn($productEntitySearchResult);

        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock($this);

        $containerRepositoriesMap = [
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['order_line_item.repository', $this->getContainer()->get('order_line_item.repository')],
            ['product.repository', $productRepositoryMock],
            [SystemConfigService::class, $configServiceMock],
            [FindologicProductFactory::class, new FindologicProductFactory()]
        ];
        $containerMock->method('get')->willReturnMap($containerRepositoriesMap);

        $this->exportController->setContainer($containerMock);

        $result = $this->exportController->export($request, $salesChannelContextMock);

        $this->assertEquals(200, $result->getStatusCode());
        $xml = new SimpleXMLElement($result->getContent());
        $this->assertSame(1, (int)$xml->items[0]->attributes()['count']);
        $this->assertSame($productEntity->getId(), (string)$xml->items->item['id']);
    }

    public function crossSellingCategoryProvider()
    {
        $categoryOne = '3f48c6c920e9972270b9730f97b395d2';
        $categoryTwo = 'bd466201e971ead1265c2bb4ab72d5df';
        $notInCrossSellingCategory = '0f26d5374acb10dddb2f3a027e92b85d';

        return [
            'No cross-sell categories configured' => [
                'assignedCategory' => [
                    [
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
                        'id' => $categoryOne,
                        'name' => 'Category 1',
                    ],
                    [
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

        $repositories['product.repository'] = $productRepositoryMock;
        $repositories[SystemConfigService::class] = $configServiceMock;

        $containerMock = $this->getContainerMock($repositories);
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
        $start = 0;
        $count = 20;

        $salesChannelId = Defaults::SALES_CHANNEL;

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->once())->method('getConfigurationValue')->willReturn($this->validShopkey);
        $systemConfigEntity->expects($this->exactly(2))->method('getSalesChannelId')->willReturn($salesChannelId);

        /** @var SystemConfigCollection $entities */
        $entities = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $entitySearchResult */
        $entitySearchResult
            = new EntitySearchResult(1, $entities, null, new Criteria(), $this->defaultContext);

        $systemConfigRepositoryMock->expects($this->once())->method('search')->willReturn($entitySearchResult);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $salesChannelContextMock->expects($this->once())
            ->method('getContext')
            ->willReturn($this->defaultContext);

        /** @var Request $request */
        $request = new Request(['shopkey' => $this->validShopkey, 'start' => $start, 'count' => $count]);

        /** @var PsrContainerInterface|MockObject $containerMock */
        $containerMock
            = $this->getMockBuilder(PsrContainerInterface::class)->disableOriginalConstructor()->getMock();

        /** @var EntityRepository|MockObject $productRepositoryMock */
        $productRepositoryMock
            = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();

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

        $productIdSearchResult = new IdSearchResult(
            13,
            [],
            $criteria,
            $this->defaultContext
        );

        $criteria = Utils::addProductAssociations($criteria);
        $criteria->setOffset($start);
        $criteria->setLimit($count);

        /** @var EntitySearchResult $productEntitySearchResult */
        $productEntitySearchResult = new EntitySearchResult(
            1,
            $productCollection,
            null,
            $criteria,
            $this->defaultContext
        );

        $productRepositoryMock->expects($this->once())
            ->method('searchIds')
            ->willReturn($productIdSearchResult);
        $productRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($productEntitySearchResult);

        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock($this);

        $containerRepositoriesMap = [
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['product.repository', $productRepositoryMock],
            ['sales_channel.repository', $this->getContainer()->get('sales_channel.repository')],
            ['currency.repository', $this->getContainer()->get('currency.repository')],
            ['customer.repository', $this->getContainer()->get('customer.repository')],
            ['country.repository', $this->getContainer()->get('country.repository')],
            ['tax.repository', $this->getContainer()->get('tax.repository')],
            ['customer_address.repository', $this->getContainer()->get('customer_address.repository')],
            ['payment_method.repository', $this->getContainer()->get('payment_method.repository')],
            ['shipping_method.repository', $this->getContainer()->get('shipping_method.repository')],
            ['country_state.repository', $this->getContainer()->get('country_state.repository')],
            ['order_line_item.repository', $this->getContainer()->get('order_line_item.repository')],
            [SystemConfigService::class, $configServiceMock],
            [FindologicProductFactory::class, $this->getContainer()->get(FindologicProductFactory::class)],
            [SalesChannelContextFactory::class, $this->getContainer()->get(SalesChannelContextFactory::class)],
        ];
        $containerMock->method('get')->willReturnMap($containerRepositoriesMap);

        $this->exportController->setContainer($containerMock);
        $result = $this->exportController->export($request, $salesChannelContextMock);

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

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->once())->method('getConfigurationValue')->willReturn($this->validShopkey);
        $systemConfigEntity->expects($this->never())->method('getSalesChannelId')->willReturn($this->validShopkey);

        /** @var SystemConfigCollection $entities */
        $entities = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $systemConfigSearchResult */
        $systemConfigSearchResult = new EntitySearchResult(
            1,
            $entities,
            null,
            new Criteria(),
            $this->defaultContext
        );

        $systemConfigRepositoryMock->expects($this->once())->method('search')->willReturn($systemConfigSearchResult);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock
            = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

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
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);

        $salesChannelContextMock->expects($this->exactly(5))
            ->method('getContext')
            ->willReturn($this->defaultContext);

        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelMock);

        /** @var Request $request */
        $request = new Request(['shopkey' => $this->validShopkey, 'start' => 0, 'count' => 20]);

        /** @var PsrContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(PsrContainerInterface::class)
            ->disableOriginalConstructor()->getMock();

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

        $productRepositoryMock->expects($this->at(0))
            ->method('searchIds')
            ->with($criteriaWithoutOffsetLimit, $this->defaultContext)
            ->willReturn($productIdSearchResult);
        $productRepositoryMock->expects($this->at(1))
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
            [SystemConfigService::class, $configServiceMock],
            [FindologicProductFactory::class, new FindologicProductFactory()]
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
}
