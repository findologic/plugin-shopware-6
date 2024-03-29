<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\Shopware6Common\Export\Enums\AdvancedPricing;
use FINDOLOGIC\Shopware6Common\Export\Enums\MainVariant;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Defaults;

trait ConfigHelper
{
    public function getShopkey(): string
    {
        return '74B87337454200D4D33F80C4663DC5E5';
    }

    public function getConfig(bool $assoc = true, string $fileName = 'di_config.json')
    {
        $config = file_get_contents(__DIR__ . '/../../MockData/ConfigResponse/' . $fileName);
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
        array $overrides = [],
        array $removeKeys = []
    ): FindologicConfigService {
        /** @var FindologicConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->createMock(FindologicConfigService::class);

        $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
        $languageId = Defaults::LANGUAGE_SYSTEM;
        if (isset($overrides['salesChannelId'])) {
            $salesChannelId = $overrides['salesChannelId'];
            unset($overrides['salesChannelId']);
        }
        if (isset($overrides['languageId'])) {
            $languageId = $overrides['languageId'];
            unset($overrides['languageId']);
        }

        $defaultConfig = [
            'active' => true,
            'isStaging' => false,
            'shopkey' => $this->getShopkey(),
            'activeOnCategoryPages' => true,
            'crossSellingCategories' => [],
            'searchResultContainer' => '.fl-result',
            'navigationResultContainer' => '.fl-navigation-result',
            'integrationType' => 'Direct Integration',
            'mainVariant' => MainVariant::SHOPWARE_DEFAULT->value,
            'advancedPricing' => AdvancedPricing::OFF->value,
            'exportZeroPricedProducts' => false
        ];

        $config = array_merge($defaultConfig, $overrides);

        $returnMap = [];
        foreach ($config as $configName => $configValue) {
            if (!in_array($configName, $removeKeys)) {
                $returnMap[] = [
                    'FinSearch.config.' . $configName,
                    $salesChannelId,
                    $languageId,
                    $configValue
                ];
            }
        }

        $configServiceMock->method('get')
            ->willReturnMap($returnMap);

        return $configServiceMock;
    }
}
