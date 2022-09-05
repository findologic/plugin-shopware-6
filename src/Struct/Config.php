<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\FilterPosition;
use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Findologic\MainVariant;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use GuzzleHttp\Exception\ClientException;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Config extends Struct
{
    public const DEFAULT_SEARCH_RESULT_CONTAINER = 'fl-result';
    public const DEFAULT_NAVIGATION_RESULT_CONTAINER = 'fl-navigation-result';
    public const ALLOW_FOR_SERIALIZATION = [
        'shopkey',
        'active',
        'staging',
        'activeOnCategoryPages',
        'crossSellingCategories',
        'searchResultContainer',
        'navigationResultContainer',
        'integrationType',
        'initialized',
        'filterPosition',
        'mainVariant'
    ];

    private FindologicConfigService $systemConfigService;

    private ?string $shopkey;

    private bool $active;

    private bool $activeOnCategoryPages;

    private string $searchResultContainer;

    private string $navigationResultContainer;

    private ?string $integrationType = null;

    private ServiceConfigResource $serviceConfigResource;

    private bool $staging;

    private bool $initialized = false;

    private string $filterPosition;

    private string $mainVariant = MainVariant::SHOPWARE_DEFAULT;

    private array $crossSellingCategories = [];

    public function __construct(
        FindologicConfigService $systemConfigService,
        ServiceConfigResource $serviceConfigResource
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->serviceConfigResource = $serviceConfigResource;
    }

    public function __sleep(): array
    {
        // Only return instances that are actually serializable. For example the SystemConfigService is not
        // serializable, as it has an PDO instance associated to it.
        return self::ALLOW_FOR_SERIALIZATION;
    }

    public function getShopkey(): ?string
    {
        return $this->shopkey;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isStaging(): bool
    {
        return $this->staging;
    }

    public function isActiveOnCategoryPages(): bool
    {
        return $this->activeOnCategoryPages;
    }

    public function getSearchResultContainer(): string
    {
        return $this->searchResultContainer;
    }

    public function getNavigationResultContainer(): string
    {
        return $this->navigationResultContainer;
    }

    public function getIntegrationType(): ?string
    {
        return $this->integrationType;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function initializeBySalesChannel(SalesChannelContext $salesChannelContext): void
    {
        $salesChannel = $salesChannelContext->getSalesChannel();
        $salesChannelId = $salesChannel->getId();
        $languageId = $salesChannel->getLanguageId();

        $this->active = $this->getConfig($salesChannelId, $languageId, 'FinSearch.config.active', false);
        $this->shopkey = $this->getConfig($salesChannelId, $languageId, 'FinSearch.config.shopkey');
        $this->activeOnCategoryPages = $this->getConfig(
            $salesChannelId,
            $languageId,
            'FinSearch.config.activeOnCategoryPages',
            false
        );
        $this->crossSellingCategories = $this->getConfig(
            $salesChannelId,
            $languageId,
            'FinSearch.config.crossSellingCategories',
            []
        );
        $this->searchResultContainer = $this->getConfig(
            $salesChannelId,
            $languageId,
            'FinSearch.config.searchResultContainer',
            self::DEFAULT_SEARCH_RESULT_CONTAINER
        );
        $this->navigationResultContainer = $this->getConfig(
            $salesChannelId,
            $languageId,
            'FinSearch.config.navigationResultContainer',
            self::DEFAULT_NAVIGATION_RESULT_CONTAINER
        );
        $this->filterPosition = $this->getConfig(
            $salesChannelId,
            $languageId,
            'FinSearch.config.filterPosition',
            FilterPosition::TOP
        );
        $this->mainVariant = $this->getConfig(
            $salesChannelId,
            $languageId,
            'FinSearch.config.mainVariant',
            MainVariant::SHOPWARE_DEFAULT
        );

        $this->initializeReadonlyConfig($salesChannelId, $languageId);

        $this->initialized = true;
    }

    public function getCrossSellingCategories(): array
    {
        return $this->crossSellingCategories;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function initializeReadonlyConfig(?string $salesChannelId, ?string $languageId): void
    {
        try {
            // Only set read-only configurations if the plugin is active
            if ($this->active) {
                $isDirectIntegration = $this->serviceConfigResource->isDirectIntegration($this->shopkey);
                $this->integrationType = $isDirectIntegration ? IntegrationType::DI : IntegrationType::API;
                $integrationType = $this->getConfig($salesChannelId, $languageId, 'FinSearch.config.integrationType');

                if ($this->integrationType !== $integrationType) {
                    $this->systemConfigService->set(
                        'FinSearch.config.integrationType',
                        $this->integrationType,
                        $salesChannelId,
                        $languageId
                    );
                }

                $this->staging = $this->getConfig($salesChannelId, $languageId, 'FinSearch.config.isStaging');
                $isStaging = $this->serviceConfigResource->isStaging($this->shopkey);

                if ($this->staging !== $isStaging) {
                    $this->staging = $isStaging;
                    $this->systemConfigService->set(
                        'FinSearch.config.isStaging',
                        $this->staging,
                        $salesChannelId,
                        $languageId
                    );
                }
            }
        } catch (ClientException $e) {
            $this->staging = false;
            $this->integrationType = null;
        }
    }

    /**
     * @return string|bool|array|null
     */
    private function getConfig(?string $salesChannelId, ?string $languageId, string $configKey, $default = null)
    {
        $configValue = $this->systemConfigService->get($configKey, $salesChannelId, $languageId);
        if ($configValue === null || (is_string($configValue) && trim($configValue) === '')) {
            return $default;
        }

        return $configValue;
    }

    public function getMainVariant(): string
    {
        return $this->mainVariant;
    }

    /**
     * @return string
     */
    public function getFilterPosition(): string
    {
        return $this->filterPosition;
    }

    public function isIntegrationTypeDirectIntegration(): bool
    {
        return $this->integrationType === IntegrationType::DI;
    }

    public function isIntegrationTypeApi(): bool
    {
        return $this->integrationType === IntegrationType::API;
    }
}
