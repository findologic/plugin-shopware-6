<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SimpleXMLElement;

trait ConfigHelper
{
    public function getShopkey(): string
    {
        return '74B87337454200D4D33F80C4663DC5E5';
    }

    public function getConfig(bool $assoc = true)
    {
        $config = file_get_contents(__DIR__ . '/../../MockData/ConfigResponse/example_config.json');
        if ($assoc) {
            return json_decode($config, true);
        }

        return $config;
    }

    public function getDemoXMLResponse(): string
    {
        return file_get_contents(__DIR__ . '/../../MockData/XMLResponse/demo.xml');
    }

    public function getDemoXML(): SimpleXMLElement
    {
        return new SimpleXMLElement($this->getDemoXMLResponse());
    }

    /**
     * Creates a system config service mock with default findologic config values initialized
     * Passing the data array will override any default values if needed
     */
    private function getDefaultFindologicConfigServiceMock(
        TestCase $testClass,
        array $overrides = []
    ): SystemConfigService {
        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $testClass->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $defaultConfig = [
            'active' => true,
            'shopkey' => $this->getShopkey(),
            'activeOnCategoryPages' => true,
            'searchResultContainer' => 'fl-result',
            'navigationResultContainer' => 'fl-navigation-result',
            'integrationType' => 'Direct Integration',
        ];

        $config = array_merge($defaultConfig, $overrides);

        $configServiceMock->method('get')
            ->willReturnOnConsecutiveCalls(
                $config['active'],
                $config['shopkey'],
                $config['activeOnCategoryPages'],
                $config['searchResultContainer'],
                $config['navigationResultContainer'],
                $config['integrationType']
            );

        return $configServiceMock;
    }
}
