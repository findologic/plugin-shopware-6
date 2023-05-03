<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\FilterPosition;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\Shopware6Common\Export\Enums\AdvancedPricing;
use FINDOLOGIC\Shopware6Common\Export\Enums\IntegrationType;
use FINDOLOGIC\Shopware6Common\Export\Enums\MainVariant;
use GuzzleHttp\Exception\ClientException;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Config extends Struct
{
    public const DEFAULT_SEARCH_RESULT_CONTAINER = '.fl-result';
    public const DEFAULT_NAVIGATION_RESULT_CONTAINER = '.fl-navigation-result';
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
        'mainVariant',
        'advancedPricing',
        'exportZeroPricedProducts',
        'useXmlVariants',
    ];

    protected ?string $shopkey;

    protected bool $active;

    protected bool $activeOnCategoryPages;

    protected string $searchResultContainer;

    protected string $navigationResultContainer;

    protected ?IntegrationType $integrationType = null;

    protected ?bool $staging = null;

    protected bool $initialized = false;

    protected string $filterPosition;

    protected MainVariant $mainVariant = MainVariant::SHOPWARE_DEFAULT;

    protected array $crossSellingCategories = [];

    protected AdvancedPricing $advancedPricing = AdvancedPricing::OFF;

    protected bool $exportZeroPricedProducts = false;

    protected bool $useXmlVariants = false;

    public function __construct(
        private readonly FindologicConfigService $systemConfigService,
        private readonly ServiceConfigResource $serviceConfigResource
    ) {
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

    public function getIntegrationType(): ?IntegrationType
    {
        return $this->integrationType;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function getCrossSellingCategories(): array
    {
        return $this->crossSellingCategories;
    }

    public function getMainVariant(): MainVariant
    {
        return $this->mainVariant;
    }

    public function getFilterPosition(): string
    {
        return $this->filterPosition;
    }

    public function getAdvancedPricing(): AdvancedPricing
    {
        return $this->advancedPricing;
    }

    public function shouldExportZeroPricedProducts(): bool
    {
        return $this->exportZeroPricedProducts;
    }

    public function useXmlVariants(): bool
    {
        return $this->useXmlVariants;
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
        $this->mainVariant = MainVariant::from(
            $this->getConfig(
                $salesChannelId,
                $languageId,
                'FinSearch.config.mainVariant',
                MainVariant::SHOPWARE_DEFAULT->value
            )
        );
        $this->advancedPricing = AdvancedPricing::from(
            $this->getConfig(
                $salesChannelId,
                $languageId,
                'FinSearch.config.advancedPricing',
                AdvancedPricing::OFF->value
            )
        );
        $this->exportZeroPricedProducts = $this->getConfig(
            $salesChannelId,
            $languageId,
            'FinSearch.config.exportZeroPricedProducts',
            false
        );
        $this->useXmlVariants = $this->getConfig(
            $salesChannelId,
            $languageId,
            'FinSearch.config.useXmlVariants',
            false
        );

        $this->initializeReadonlyConfig($salesChannelId, $languageId);

        $this->initialized = true;
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

                if ($this->integrationType->value !== $integrationType) {
                    $this->systemConfigService->set(
                        'FinSearch.config.integrationType',
                        $this->integrationType->value,
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

    private function getConfig(
        ?string $salesChannelId,
        ?string $languageId,
        string $configKey,
        mixed $default = null
    ): mixed {
        $configValue = $this->systemConfigService->get($configKey, $salesChannelId, $languageId);
        if ($configValue === null || (is_string($configValue) && trim($configValue) === '')) {
            return $default;
        }

        return $configValue;
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
