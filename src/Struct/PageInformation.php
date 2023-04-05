<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class PageInformation extends Struct
{
    public function __construct(
        private readonly bool $isSearchPage,
        private readonly bool $isNavigationPage
    ) {
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
