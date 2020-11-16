<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\System\SystemConfig\SystemConfigService;

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

    /**
     * Creates a system config service mock with default findologic config values initialized
     * Passing the data array will override any default values if needed
     */
    private function getDefaultFindologicConfigServiceMock(
        TestCase $testClass,
        array $overrides = []
    ): SystemConfigService {
        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->createMock(SystemConfigService::class);
        $salesChannelId = Defaults::SALES_CHANNEL;
        if (isset($overrides['salesChannelId'])) {
            $salesChannelId = $overrides['salesChannelId'];
            unset($overrides['salesChannelId']);
        }
        $defaultConfig = [
            'active' => true,
            'shopkey' => $this->getShopkey(),
            'activeOnCategoryPages' => true,
            'crossSellingCategories' => [],
            'searchResultContainer' => 'fl-result',
            'navigationResultContainer' => 'fl-navigation-result',
            'integrationType' => 'Direct Integration',
        ];

        $config = array_merge($defaultConfig, $overrides);

        $configServiceMock->method('get')
            ->willReturnMap(
                [
                    ['FinSearch.config.active', $salesChannelId, $config['active']],
                    ['FinSearch.config.shopkey', $salesChannelId, $config['shopkey']],
                    ['FinSearch.config.activeOnCategoryPages', $salesChannelId, $config['activeOnCategoryPages']],
                    ['FinSearch.config.crossSellingCategories', $salesChannelId, $config['crossSellingCategories']],
                    ['FinSearch.config.searchResultContainer', $salesChannelId, $config['searchResultContainer']],
                    [
                        'FinSearch.config.navigationResultContainer',
                        $salesChannelId,
                        $config['navigationResultContainer']
                    ],
                    ['FinSearch.config.integrationType', $salesChannelId, $config['integrationType']]
                ]
            );

        return $configServiceMock;
    }
}
