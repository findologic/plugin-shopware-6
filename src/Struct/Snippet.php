<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Snippet extends Struct
{
    /** @var string */
    private $searchResultContainer;

    /** @var string */
    private $navigationResultContainer;

    /** @var string */
    private $userGroupHash;

    /** @var string */
    private $hashedShopkey;

    public function __construct(
        string $shopkey,
        string $searchResultContainer,
        string $navigationResultContainer,
        string $userGroupHash
    ) {
        $this->hashedShopkey = strtoupper(md5($shopkey));
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

    public function getHashedShopkey(): string
    {
        return $this->hashedShopkey;
    }
}
