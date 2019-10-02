<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Controller\ExportController;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Symfony\Component\HttpFoundation\Request;

class ExportControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ExportController
     */
    private $exportController;

    protected function setUp(): void
    {
        parent::setUp();

        $loggerMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->exportController = new ExportController($loggerMock);
    }

    /**
     * @dataProvider invalidArgumentProvider
     * @dataProvider validArgumentProvider
     */
    public function testExport(string $shopkey, $start, $count, bool $checkResponse = false)
    {
        if (!$checkResponse) {
            $this->expectException(InvalidArgumentException::class);
        }

        $systemConfigRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->method('getConfigurationKey')->willReturn($shopkey);
        $systemConfigEntity->method('getSalesChannelId')->willReturn(null);

        $configs = new SystemConfigCollection([$systemConfigEntity]);

        $systemConfigRepositoryMock->method('search')->willReturn($configs);
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelContextMock->method('getToken')->willReturn('token');

        $request = new Request(['shopkey' => $shopkey, 'start' => $start, 'count' => $count]);

        $containerMock = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $containerMock->method('get')->willReturn($systemConfigRepositoryMock);

        $this->exportController->setContainer($containerMock);
        $response = $this->exportController->export($request, $salesChannelContextMock);

        if ($checkResponse) {
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function invalidArgumentProvider(): array
    {
        return [
            'No shopkey was provided' => ['shopkey' => '', 'start' => 1, 'count' => 20],
            'Malformed shopkey provided' => ['shopkey' => 'ABCD01815', 'start' => 1, 'count' => 20],
            '"count" parameter is some string' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 1,
                'count' => 'some string'
            ],
            '"count" parameter is zero' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 1,
                'count' => 0
            ],
            '"count" parameter is a negative number' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 1,
                'count' => -1
            ],
            '"start" parameter is some string' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 'some string',
                'count' => 20
            ],
            '"start" parameter is a negative number' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => -1,
                'count' => 20
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
                'response' => true
            ],
            '"start" parameter is zero with well formed shopkey' => [
                'shopkey' => '80AB18D4BE2654E78244106AD315DC2C',
                'start' => 0,
                'count' => 20,
                'response' => true
            ],
        ];
    }
}
