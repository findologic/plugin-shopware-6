<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

trait ConfigHelper
{
    public function getShopkey(): string
    {
        return '74B87337454200D4D33F80C4663DC5E5';
    }

    public function getConfig(bool $assoc = true)
    {
        $config = file_get_contents(__DIR__ . '/example_config.json');
        if ($assoc) {
            return json_decode($config, true);
        }

        return $config;
    }

    public function getDemoXMLResponse(): string
    {
        return file_get_contents(__DIR__ . '/demo.xml');
    }

    /**
     * Creates a system config service mock with default findologic config values initialized
     * Passing the data array will override any default values if needed
     */
    private function getDefaultFindologicConfigServiceMock(TestCase $testClass, array $data = [])
    {
        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $testClass->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $active = $data['active'] ?? true;
        $shopkey = $data['shopkey'] ?? $this->getShopkey();
        $activeOnCategoryPages = $data['activeOnCategoryPages'] ?? true;
        $searchResultContainer = $data['searchResultContainer'] ?? 'fl-result';
        $navigationResultContainer = $data['navigationResultContainer'] ?? 'fl-navigation-result';
        $integrationType = $data['integrationType'] ?? 'Direct Integration';

        $configServiceMock->method('get')
            ->willReturnOnConsecutiveCalls(
                $active,
                $shopkey,
                $activeOnCategoryPages,
                $searchResultContainer,
                $navigationResultContainer,
                $integrationType
            );

        return $configServiceMock;
    }
}
