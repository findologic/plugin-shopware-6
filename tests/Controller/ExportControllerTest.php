<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Controller\ExportController;
use FINDOLOGIC\FinSearch\Exceptions\UnknownShopkeyException;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Tests\ProductHelper;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Storefront\Framework\Routing\Router;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;

class ExportControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;

    /** @var ExportController */
    private $exportController;

    /** @var Logger|MockObject */
    private $loggerMock;

    /** @var Context */
    private $defaultContext;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Router $router */
        $router = $this->getContainer()->get('router');

        $this->loggerMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->exportController = new ExportController($this->loggerMock, $router);
        $this->defaultContext = Context::createDefaultContext();
    }

    public function invalidArgumentProvider(): array
    {
        $validShopkey = '80AB18D4BE2654E78244106AD315DC2C';

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
                'shopkey' => $validShopkey,
                'start' => 1,
                'count' => 0,
                'exceptionMessage' => 'The value 0 is not greater than zero'
            ],
            '"count" parameter is some string' => [
                'shopkey' => $validShopkey,
                'start' => 'some string',
                'count' => 20,
                'exceptionMessage' => 'The value "some string" is not a valid numeric'
            ],
            '"count" parameter is a negative number' => [
                'shopkey' => $validShopkey,
                'start' => 1,
                'count' => -1,
                'exceptionMessage' => 'The value -1 is not greater than zero'
            ],
            '"start" parameter is some string' => [
                'shopkey' => $validShopkey,
                'start' => 'some string',
                'count' => 20,
                'exceptionMessage' => 'The value "some string" is not a valid numeric'
            ],
            '"start" parameter is a negative number' => [
                'shopkey' => $validShopkey,
                'start' => -1,
                'count' => 20,
                'exceptionMessage' => 'The value -1 is not greater than or equal to zero'
            ],
        ];
    }

    /**
     * @param int|string $start
     * @param int|string $count
     *
     * @dataProvider invalidArgumentProvider
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
        $salesChannelContextMock =
            $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $shopkey, 'start' => $start, 'count' => $count]);

        $this->exportController->export($request, $salesChannelContextMock);
    }

    public function validArgumentProvider(): array
    {
        $validShopkey = '80AB18D4BE2654E78244106AD315DC2C';

        return [
            'Well formed shopkey provided' => [
                'shopkey' => $validShopkey,
                'start' => 1,
                'count' => 20
            ],
            '"start" parameter is zero with well formed shopkey' => [
                'shopkey' => $validShopkey,
                'start' => 0,
                'count' => 20
            ],
        ];
    }

    /**
     * @dataProvider validArgumentProvider
     */
    public function testExportWithValidArguments(
        string $shopkey,
        int $start,
        int $count
    ): void {
        $salesChannelId = Uuid::randomHex();

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock =
            $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);

        $salesChannelContextMock->expects($this->exactly(5))
            ->method('getContext')
            ->willReturn($this->defaultContext);

        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelMock);

        /** @var Request $request */
        $request = new Request(['shopkey' => $shopkey, 'start' => $start, 'count' => $count]);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock =
            $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->once())->method('getConfigurationValue')->willReturn($shopkey);
        $systemConfigEntity->expects($this->once())->method('getSalesChannelId')->willReturn(null);

        /** @var SystemConfigCollection $systemConfigCollection */
        $systemConfigCollection = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $systemConfigEntitySearchResult */
        $systemConfigEntitySearchResult =
            new EntitySearchResult(1, $systemConfigCollection, null, new Criteria(), $this->defaultContext);

        $systemConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($systemConfigEntitySearchResult);

        /** @var EntityRepository|MockObject $productRepositoryMock */
        $productRepositoryMock =
            $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();

        /** @var ProductEntity $productEntity */
        $productEntity = $this->createTestProduct();

        $this->assertInstanceOf(ProductEntity::class, $productEntity);

        /** @var ProductCollection $productCollection */
        $productCollection = new ProductCollection([$productEntity]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parent.id', null));
        $criteria->addFilter(new ProductAvailableFilter(
            $salesChannelId,
            ProductVisibilityDefinition::VISIBILITY_SEARCH
        ));

        $criteria->addAssociation('categories');
        $criteria->addAssociation('children');
        $criteria->setOffset($start);
        $criteria->setLimit($count);

        /** @var EntitySearchResult $productEntitySearchResult */
        $productEntitySearchResult =
            new EntitySearchResult(1, $productCollection, null, $criteria, $this->defaultContext);

        $criteriaWithoutOffsetLimit = clone $criteria;
        $criteriaWithoutOffsetLimit->setOffset(null);
        $criteriaWithoutOffsetLimit->setLimit(null);

        $productRepositoryMock->expects($this->at(0))
            ->method('search')
            ->with($criteriaWithoutOffsetLimit, $this->defaultContext)
            ->willReturn($productEntitySearchResult);
        $productRepositoryMock->expects($this->at(1))
            ->method('search')
            ->with($criteria, $this->defaultContext)
            ->willReturn($productEntitySearchResult);

        $containerRepositoriesMap = [
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['product.repository', $productRepositoryMock],
            [FindologicProductFactory::class, new FindologicProductFactory()]
        ];
        $containerMock->method('get')->willReturnMap($containerRepositoriesMap);

        $this->exportController->setContainer($containerMock);

        $result = $this->exportController->export($request, $salesChannelContextMock);

        $this->assertEquals(200, $result->getStatusCode());
        $xml = new SimpleXMLElement($result->getContent());
        $this->assertSame(1, $xml->items->count());
        $this->assertSame($productEntity->getId(), (string)$xml->items->item['id']);
    }

    public function testExportWithSalesChannelId(): void
    {
        $shopkey = '80AB18D4BE2654E78244106AD315DC2C';
        $start = 0;
        $count = 20;

        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('sales_channel.repository');

        $criteria = (new Criteria())->setLimit(1);
        $salesChannelId = $repository->searchIds($criteria, $this->defaultContext)->getIds()[0];

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock =
            $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->once())->method('getConfigurationValue')->willReturn($shopkey);
        $systemConfigEntity->expects($this->exactly(2))->method('getSalesChannelId')->willReturn($salesChannelId);

        /** @var SystemConfigCollection $entities */
        $entities = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $entitySearchResult */
        $entitySearchResult =
            new EntitySearchResult(1, $entities, null, new Criteria(), $this->defaultContext);

        $systemConfigRepositoryMock->expects($this->once())->method('search')->willReturn($entitySearchResult);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock =
            $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        $salesChannelContextMock->expects($this->once())
            ->method('getContext')
            ->willReturn($this->defaultContext);

        /** @var Request $request */
        $request = new Request(['shopkey' => $shopkey, 'start' => $start, 'count' => $count]);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();

        $containerRepositoriesMap = [
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['product.repository', $this->getContainer()->get('product.repository')],
            ['sales_channel.repository', $this->getContainer()->get('sales_channel.repository')],
            ['currency.repository', $this->getContainer()->get('currency.repository')],
            ['customer.repository', $this->getContainer()->get('customer.repository')],
            ['country.repository', $this->getContainer()->get('country.repository')],
            ['tax.repository', $this->getContainer()->get('tax.repository')],
            ['customer_address.repository', $this->getContainer()->get('customer_address.repository')],
            ['payment_method.repository', $this->getContainer()->get('payment_method.repository')],
            ['shipping_method.repository', $this->getContainer()->get('shipping_method.repository')],
            ['country_state.repository', $this->getContainer()->get('country_state.repository')],
        ];
        $containerMock->method('get')->willReturnMap($containerRepositoriesMap);

        $this->exportController->setContainer($containerMock);
        $result = $this->exportController->export($request, $salesChannelContextMock);

        $this->assertEquals(200, $result->getStatusCode());
    }

    public function testExportWithUnknownShopkey(): void
    {
        $shopkey = '80AB18D4BE2654E78244106AD315DC2C';
        $unknownShopkey = '80AB18D4BE2654E78244106AD315DCCC';

        $this->expectException(UnknownShopkeyException::class);
        $this->expectExceptionMessage(sprintf('Given shopkey "%s" is not assigned to any shop', $unknownShopkey));

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock =
            $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->once())->method('getConfigurationValue')->willReturn($shopkey);
        $systemConfigEntity->expects($this->never())->method('getSalesChannelId')->willReturn($shopkey);

        /** @var SystemConfigCollection $entities */
        $entities = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $systemConfigSearchResult */
        $systemConfigSearchResult = new EntitySearchResult(1, $entities, null, new Criteria(), $this->defaultContext);

        $systemConfigRepositoryMock->expects($this->once())->method('search')->willReturn($systemConfigSearchResult);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock =
            $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $unknownShopkey]);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->once())->method('get')->willReturn($systemConfigRepositoryMock);

        $this->exportController->setContainer($containerMock);
        $this->exportController->export($request, $salesChannelContextMock);
    }
}
