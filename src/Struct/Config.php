<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use GuzzleHttp\Exception\ClientException;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Config extends Struct
{
    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var string */
    private $shopkey;

    /** @var bool */
    private $active;

    /** @var bool */
    private $activeOnCategoryPages;

    /** @var string */
    private $searchResultContainer;

    /** @var string */
    private $navigationResultContainer;

    /** @var string|null */
    private $integrationType;

    /** @var ServiceConfigResource */
    private $serviceConfigResource;

    /**@var bool */
    private $staging;

    /** @var bool */
    private $initialized = false;

    public function __construct(SystemConfigService $systemConfigService, ServiceConfigResource $serviceConfigResource)
    {
        $this->systemConfigService = $systemConfigService;
        $this->serviceConfigResource = $serviceConfigResource;
    }

    public function getShopkey(): string
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
    public function initializeBySalesChannel(?string $salesChannelId): void
    {
        $this->active = $this->systemConfigService->get(
            'FinSearch.config.active',
            $salesChannelId
        ) ?? false;
        $this->shopkey = $this->systemConfigService->get(
            'FinSearch.config.shopkey',
            $salesChannelId
        );
        $this->activeOnCategoryPages = $this->systemConfigService->get(
            'FinSearch.config.activeOnCategoryPages',
            $salesChannelId
        );
        $this->searchResultContainer = $this->systemConfigService->get(
            'FinSearch.config.searchResultContainer',
            $salesChannelId
        ) ?? 'fl-result';
        $this->navigationResultContainer = $this->systemConfigService->get(
            'FinSearch.config.navigationResultContainer',
            $salesChannelId
        ) ?? 'fl-navigation-result';

        $this->initializeReadonlyConfig($salesChannelId);

        $this->initialized = true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function initializeReadonlyConfig(?string $salesChannelId): void
    {
        try {
            // Only set read-only configurations if the plugin is active
            if ($this->active) {
                $isDirectIntegration = $this->serviceConfigResource->isDirectIntegration($this->shopkey);
                $this->integrationType = $isDirectIntegration ? IntegrationType::DI : IntegrationType::API;
                $integrationType = $this->systemConfigService->get('FinSearch.config.integrationType', $salesChannelId);

                if ($this->integrationType !== $integrationType) {
                    $this->systemConfigService->set(
                        'FinSearch.config.integrationType',
                        $this->integrationType,
                        $salesChannelId
                    );
                }

                $this->staging = $this->systemConfigService->get('FinSearch.config.isStaging', $salesChannelId);
                $isStaging = $this->serviceConfigResource->isStaging($this->shopkey);

                if ($this->staging !== $isStaging) {
                    $this->staging = $isStaging;
                    $this->systemConfigService->set(
                        'FinSearch.config.isStaging',
                        $this->staging,
                        $salesChannelId
                    );
                }
            }
        } catch (ClientException $e) {
            $this->staging = false;
            $this->integrationType = null;
        }
    }
}
