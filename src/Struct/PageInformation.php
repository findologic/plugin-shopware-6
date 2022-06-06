<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class PageInformation extends Struct
{
    /** @var bool */
    private $isSearchPage;

    /** @var bool */
    private $isNavigationPage;

    public function __construct(bool $isSearchPage, bool $isNavigationPage)
    {
        $this->isSearchPage = $isSearchPage;
        $this->isNavigationPage = $isNavigationPage;
    }

    public function getIsSearchPage(): bool
    {
        return $this->isSearchPage;
    }

    public function getIsNavigationPage(): bool
    {
        return $this->isNavigationPage;
    }
}