<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Controller\ExportController;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
     */
    public function testExport(string $shopkey, $start, $count)
    {
        $this->expectException(InvalidArgumentException::class);

        $entityRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entitySearchResultMock = $this->getMockBuilder(EntitySearchResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $entityRepositoryMock->method('search')->willReturn($entitySearchResultMock);
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelContextMock->method('getToken')->willReturn('token');

        $request = new Request(['shopkey' => $shopkey, 'start' => $start, 'count' => $count]);
        // $this->getContainer()->set('system_config.repository', $entityRepositoryMock);

        $this->exportController->setContainer($this->getContainer());
        $this->exportController->export($request, $salesChannelContextMock);
    }

    /**
     * @return mixed[]
     */
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
}
