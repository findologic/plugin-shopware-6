<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Config;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FindologicServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var FindologicConfigService
     */
    private $findologicConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->findologicConfigService = new FindologicConfigService(
            $this->getContainer()->get('finsearch_config.repository'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('sales_channel.repository')
        );
    }

    public function configurationProvider()
    {
        return [
            'Value is boolean true' => [true],
            'Value is boolean false' => [false],
            'Value is null' => [null],
            'Value is integer zero' => [0],
            'Value is integer' => [1234],
            'Value is decimal' => [1243.42314],
            'Value is empty string' => [''],
            'Value is string' => ['test'],
            'Value is array' => [['foo' => 'bar']]
        ];
    }

    /**
     * @dataProvider configurationProvider
     */
    public function testConfigurationIsSetCorrectly($expectedValue)
    {
        $this->findologicConfigService->set('foo.bar', $expectedValue);
        $actual = $this->findologicConfigService->get('foo.bar');
        static::assertSame($expectedValue, $actual);
    }

    /**
     * @dataProvider configurationProvider
     */
    public function testCorrectConfigurationReturnedForProvidedSalesChannel($expectedValue)
    {
        $this->findologicConfigService->set('foo.bar', $expectedValue, Defaults::SALES_CHANNEL);
        $actual = $this->findologicConfigService->get('foo.bar', Defaults::SALES_CHANNEL);
        static::assertSame($expectedValue, $actual);
    }

    /**
     * @dataProvider configurationProvider
     */
    public function testInheritConfigurationIsReturned($expectedValue)
    {
        $this->findologicConfigService->set('foo.bar', $expectedValue);
        $actual = $this->findologicConfigService->get('foo.bar', Defaults::SALES_CHANNEL);
        static::assertSame($expectedValue, $actual);
    }
}
