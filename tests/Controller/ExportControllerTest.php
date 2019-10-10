<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Controller\ExportController;
use FINDOLOGIC\FinSearch\Exceptions\UnknownShopkeyException;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Symfony\Component\HttpFoundation\Request;

class ExportControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /** @var ExportController */
    private $exportController;

    /** @var Logger|MockObject */
    private $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->exportController = new ExportController($this->loggerMock, $this->getContainer()->get('router'));
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
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 1,
                'count' => 0,
                'exceptionMessage' => 'The value 0 is not greater than zero'
            ],
            '"count" parameter is a negative number' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 1,
                'count' => -1,
                'exceptionMessage' => 'The value -1 is not greater than zero'
            ],
            '"start" parameter is a negative number' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => -1,
                'count' => 20,
                'exceptionMessage' => 'The value -1 is not greater than or equal to zero'
            ],
        ];
    }

    public function validArgumentProvider(): array
    {
        return [
            'Well formed shopkey provided' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 1,
                'count' => 20,
                'exceptionMessage' => null,
                'response' => true
            ],
            '"start" parameter is zero with well formed shopkey' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 0,
                'count' => 20,
                'exceptionMessage' => null,
                'response' => true
            ],
        ];
    }

    /**
     * @dataProvider invalidArgumentProvider
     * @dataProvider validArgumentProvider
     */
    public function testExportWithDifferentArguments(
        string $shopkey,
        $start,
        $count,
        ?string $exceptionMessage,
        bool $response = false
    ): void {
        if (!$response) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage($exceptionMessage);
        }

        $invokeCount = $response ? $this->atLeastOnce() : $this->never();

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock =
            $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelMock->method('getId')->willReturn('1F6E8353E5AF483593ABFBD1D319AE84');

        $salesChannelContextMock->expects($invokeCount)
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

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
        $systemConfigEntity->expects($invokeCount)->method('getConfigurationValue')->willReturn($shopkey);
        $systemConfigEntity->expects($invokeCount)->method('getSalesChannelId')->willReturn(null);

        /** @var SystemConfigCollection $entities */
        $entities = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $configs */
        $configs = new EntitySearchResult(1, $entities, null, new Criteria(), Context::createDefaultContext());

        $systemConfigRepositoryMock->expects($invokeCount)->method('search')->willReturn($configs);

        $containerRepositoriesMap = [
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['product.repository', $this->getContainer()->get('product.repository')],
        ];
        $containerMock->method('get')->willReturnMap($containerRepositoriesMap);

        $this->exportController->setContainer($containerMock);

        $result = $this->exportController->export($request, $salesChannelContextMock);

        if ($response) {
            $this->assertEquals(200, $result->getStatusCode());
        }
    }

    public function testExportWithSalesChannelId(): void
    {
        //$this->markTestSkipped();

        $salesChannelId = '1F6E8353E5AF483593ABFBD1D319AE84';
        $shopkey = 'C4FE5E0DA907E9659D3709D8CFDBAE77';
        $start = 0;
        $count = 20;

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock =
            $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->atLeastOnce())->method('getConfigurationValue')->willReturn($shopkey);
        $systemConfigEntity->expects($this->atLeastOnce())->method('getSalesChannelId')->willReturn($salesChannelId);

        /** @var SystemConfigCollection $entities */
        $entities = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $entitySearchResult */
        $entitySearchResult =
            new EntitySearchResult(1, $entities, null, new Criteria(), Context::createDefaultContext());

        $systemConfigRepositoryMock->expects($this->atLeastOnce())->method('search')->willReturn($entitySearchResult);

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock =
            $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        $salesChannelContextMock->expects($this->atLeastOnce())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelMock);

        /*$salesChannelContext = $this->getContainer()->get('sales_channel.repository')->search(new Criteria([
            $salesChannelId,
        ]), Context::createDefaultContext())->getEntities()->first();*/

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
        $shopkey = 'C4FE5E0DA907E9659D3709D8CFDBAE77';
        $unknownShopkey = '80AB18D4BE2654E782441CCCCCCCCCCC';

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

        /** @var EntitySearchResult $configs */
        $configs = new EntitySearchResult(1, $entities, null, new Criteria(), Context::createDefaultContext());

        $systemConfigRepositoryMock->expects($this->once())->method('search')->willReturn($configs);

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
