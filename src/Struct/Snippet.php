<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Snippet extends Struct
{
    private string $searchResultContainer;

    private string $navigationResultContainer;

    private string $userGroupHash;

    private string $shopkey;

    public function __construct(
        string $shopkey,
        string $searchResultContainer,
        string $navigationResultContainer,
        string $userGroupHash
    ) {
        $this->shopkey = $shopkey;
        $this->searchResultContainer = $searchResultContainer;
        $this->navigationResultContainer = $navigationResultContainer;
        $this->userGroupHash = $userGroupHash;
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
