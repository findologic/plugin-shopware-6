<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Snippet extends Struct
{
    public function __construct(
        private readonly string $shopkey,
        private readonly string $searchResultContainer,
        private readonly string $navigationResultContainer,
        private readonly string $userGroupHash
    ) {
    }

    public function getSearchResultContainer(): string
    {
        return $this->searchResultContainer;
    }

    public function getNavigationResultContainer(): string
    {
        return $this->navigationResultContainer;
    }

    public function getUserGroupHash(): string
    {
        return $this->userGroupHash;
    }

    public function getShopkey(): string
    {
        return $this->shopkey;
    }
}
