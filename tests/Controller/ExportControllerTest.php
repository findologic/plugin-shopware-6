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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
        $this->exportController = new ExportController($this->loggerMock);
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
            '"count" parameter is some string' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 1,
                'count' => 'some string',
                'exceptionMessage' => 'The value "some string" is not a valid integer'
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
            '"start" parameter is some string' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 'some string',
                'count' => 20,
                'exceptionMessage' => 'The value "some string" is not a valid integer'
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

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $invokeCount = $response ? $this->atLeastOnce() : $this->never();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($invokeCount)->method('getConfigurationValue')->willReturn($shopkey);
        $systemConfigEntity->expects($invokeCount)->method('getSalesChannelId')->willReturn(null);

        /** @var SystemConfigCollection $entities */
        $entities = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $configs */
        $configs = new EntitySearchResult(1, $entities, null, new Criteria(), Context::createDefaultContext());

        $systemConfigRepositoryMock->expects($invokeCount)->method('search')->willReturn($configs);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $shopkey, 'start' => $start, 'count' => $count]);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $containerMock->method('get')->willReturn($systemConfigRepositoryMock);

        $this->exportController->setContainer($containerMock);

        $result = $this->exportController->export($request, $salesChannelContextMock);

        if ($response) {
            $this->assertEquals(200, $result->getStatusCode());
        }
    }

    public function testExportWithSalesChannelId(): void
    {
        $salesChannelId = Uuid::randomHex();
        $shopkey = '80AB18D4BE2654E78244106AD315DC2C';
        $start = 0;
        $count = 20;

        /** @var EntityRepository|MockObject $entityRepositoryMock */
        $entityRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->atLeastOnce())->method('getConfigurationValue')->willReturn($shopkey);
        $systemConfigEntity->expects($this->atLeastOnce())->method('getSalesChannelId')->willReturn($salesChannelId);

        /** @var SystemConfigCollection $entities */
        $entities = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $entitySearchResult */
        $entitySearchResult =
            new EntitySearchResult(1, $entities, null, new Criteria(), Context::createDefaultContext());

        $entityRepositoryMock->expects($this->atLeastOnce())->method('search')->willReturn($entitySearchResult);

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $shopkey, 'start' => $start, 'count' => $count]);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $containerMock->method('get')
            ->with('system_config.repository')
            ->willReturn($entityRepositoryMock);

        $salesChannelContextMock->method('getContext')
            ->willReturn(Context::createDefaultContext());
        $salesChannelContextMock->expects($this->once())
            ->method('getToken')
            ->willReturn('tokenString');

        $this->exportController->setContainer($containerMock);
        $result = $this->exportController->export($request, $salesChannelContextMock);

        $this->assertEquals(200, $result->getStatusCode());
    }

    public function testExportWithUnknownShopkey(): void
    {
        $shopkey = '80AB18D4BE2654E78244106AD315DC2C';
        $unknownShopkey = '80AB18D4BE2654E782441CCCCCCCCCCC';

        $this->expectException(UnknownShopkeyException::class);
        $this->expectExceptionMessage(sprintf('Given shopkey "%s" is not assigned to any shop', $unknownShopkey));

        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

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
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var Request $request */
        $request = new Request(['shopkey' => $unknownShopkey]);

        /** @var ContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $containerMock->expects($this->once())->method('get')->willReturn($systemConfigRepositoryMock);

        $this->exportController->setContainer($containerMock);
        $this->exportController->export($request, $salesChannelContextMock);
    }
}
