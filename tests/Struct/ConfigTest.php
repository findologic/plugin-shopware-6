<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Defaults;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigTest extends TestCase
{
    use ConfigHelper;

    public function configValuesProvider(): array
    {
        return [
            'All properties are accessed after initialization' => [
                'data' => [
                    'active' => true,
                    'shopkey' => $this->getShopkey(),
                    'activeOnCategoryPages' => true,
                    'searchResultContainer' => 'fl-result',
                    'navigationResultContainer' => 'fl-navigation-result',
                    'integrationType' => 'API'
                ],
                'exception' => null
            ],
            'Integration type is null due to ClientException' => [
                'data' => [
                    'active' => true,
                    'shopkey' => $this->getShopkey(),
                    'activeOnCategoryPages' => true,
                    'searchResultContainer' => 'fl-result',
                    'navigationResultContainer' => 'fl-navigation-result',
                    'integrationType' => null
                ],
                'exception' => new ClientException('some message', new Request('GET', 'some url'), new Response())
            ]
        ];
    }

    /**
     * @dataProvider configValuesProvider
     *
     * @throws InvalidArgumentException
     */
    public function testConfigPropertiesInitialization(array $data, ?ClientException $exception): void
    {
        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->getDefaultFindologicConfigServiceMock($this, $data);

        /** @var ServiceConfigResource|MockObject $serviceConfigResource */
        $serviceConfigResource = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($exception !== null) {
            $serviceConfigResource->expects($this->once())
                ->method('isDirectIntegration')
                ->willThrowException($exception);
        } else {
            $serviceConfigResource->expects($this->once())
                ->method('isDirectIntegration')
                ->willReturn(false);
        }

        $config = new Config($configServiceMock, $serviceConfigResource);
        $config->initializeBySalesChannel(Defaults::SALES_CHANNEL);

        $this->assertSame($data['active'], $config->isActive());
        $this->assertSame($data['shopkey'], $config->getShopkey());
        $this->assertSame($data['activeOnCategoryPages'], $config->isActiveOnCategoryPages());
        $this->assertSame($data['searchResultContainer'], $config->getSearchResultContainer());
        $this->assertSame($data['navigationResultContainer'], $config->getNavigationResultContainer());
        $this->assertSame($data['integrationType'], $config->getIntegrationType());
    }
}
