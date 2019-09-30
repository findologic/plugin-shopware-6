<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use FINDOLOGIC\FinSearch\Controller\ExportController;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $requestMock = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();

        $requestMap = [
            ['shopkey', $shopkey],
            ['start', $start],
            ['count', $count],
        ];
        $requestMock->expects($this->exactly(4))->method('get')->willReturnMap($requestMap);

        $this->exportController->export($requestMock, $salesChannelContextMock);
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
                'shopkey' => '000000000000000ZZZZZZZZZZZZZZZZZ',
                'start' => 1,
                'count' => 'some string'
            ],
            '"count" parameter is zero' => [
                'shopkey' => '000000000000000ZZZZZZZZZZZZZZZZZ',
                'start' => 1,
                'count' => 0
            ],
            '"count" parameter is a negative number' => [
                'shopkey' => '000000000000000ZZZZZZZZZZZZZZZZZ',
                'start' => 1,
                'count' => -1
            ],
            '"start" parameter is some string' => [
                'shopkey' => '000000000000000ZZZZZZZZZZZZZZZZZ',
                'start' => 'some string',
                'count' => 20
            ],
            '"start" parameter is a negative number' => [
                'shopkey' => '000000000000000ZZZZZZZZZZZZZZZZZ',
                'start' => -1,
                'count' => 20
            ],
        ];
    }
}
