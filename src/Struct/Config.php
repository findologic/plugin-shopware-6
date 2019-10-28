<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
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

    /** @var string */
    private $integrationType;

    /** @var ServiceConfigResource */
    private $serviceConfigResource;

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

    public function getIntegrationType(): string
    {
        return $this->integrationType;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function initializeBySalesChannel(?string $salesChannelId): void
    {
        $this->shopkey = $this->systemConfigService->get(
            'FinSearch.config.shopkey',
            $salesChannelId
        );
        $this->active = $this->systemConfigService->get(
            'FinSearch.config.active',
            $salesChannelId
        ) ?? false;
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

        $isDirectIntegration = $this->serviceConfigResource->isDirectIntegration($this->shopkey);
        $integrationType = $isDirectIntegration ? IntegrationType::DIRECT_INTEGRATION : IntegrationType::API;

        if ($integrationType !== $this->systemConfigService->get('FinSearch.config.integrationType', $salesChannelId)) {
            $this->systemConfigService->set(
                'FinSearch.config.integrationType',
                $integrationType,
                $salesChannelId
            );
        }
    }
}
