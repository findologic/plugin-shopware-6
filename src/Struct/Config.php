<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

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

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->shopkey = $systemConfigService->get('FinSearch.config.shopkey');
        $this->active = $systemConfigService->get('FinSearch.config.active') ?? false;
        $this->activeOnCategoryPages = $systemConfigService->get('FinSearch.config.activeOnCategoryPages');
        $this->searchResultContainer =
            $systemConfigService->get('FinSearch.config.searchResultContainer') ?? 'fl-result';
        $this->navigationResultContainer =
            $systemConfigService->get('FinSearch.config.navigationResultContainer') ?? 'fl-navigation-result';
        $this->integrationType = $systemConfigService->get('FinSearch.config.integrationType');
        $this->systemConfigService = $systemConfigService;
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
}
