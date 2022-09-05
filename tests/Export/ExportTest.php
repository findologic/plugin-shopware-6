<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\Export;
use FINDOLOGIC\FinSearch\Export\ProductIdExport;
use FINDOLOGIC\FinSearch\Export\XmlExport;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

class ExportTest extends TestCase
{
    /** @var RouterInterface|MockObject */
    private $routerMock;

    /** @var ContainerInterface|MockObject */
    private $containerMock;

    private LoggerInterface $logger;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routerMock = $this->getMockBuilder(RouterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->containerMock = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = new Logger('fl_test_logger');
        $this->eventDispatcher = new EventDispatcher();
    }

    public function exportProvider(): array
    {
        return [
            'XML export' => [
                'type' => Export::TYPE_XML,
                'expectedInstance' => XmlExport::class
            ],
            'Product ID export' => [
                'type' => Export::TYPE_PRODUCT_ID,
                'expectedInstance' => ProductIdExport::class
            ]
        ];
    }

    /**
     * @dataProvider exportProvider
     */
    public function testProperInstanceIsCreated(int $type, string $expectedInstance): void
    {
        $export = Export::getInstance(
            $type,
            $this->routerMock,
            $this->containerMock,
            $this->logger,
            $this->eventDispatcher
        );

        $this->assertInstanceOf($expectedInstance, $export);
    }

    public function testUnknownInstanceThrowsException(): void
    {
        $unknownExportType = 420;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Unknown export type %d.', $unknownExportType));

        Export::getInstance(
            $unknownExportType,
            $this->routerMock,
            $this->containerMock,
            $this->logger,
            $this->eventDispatcher
        );
    }
}
