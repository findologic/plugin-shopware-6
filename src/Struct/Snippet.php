<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Snippet extends Struct
{
    /**
     * @var string
     */
    private $searchResultContainer;

    /**
     * @var string
     */
    private $navigationResultContainer;

    /**
     * @var string
     */
    private $userGroupHash;

    /**
     * @var string
     */
    private $hashedShopkey;

    /**
     * @param string $shopkey
     * @param string $searchResultContainer
     * @param string $navigationResultContainer
     * @param string $userGroupHash
     */
    public function __construct($shopkey, $searchResultContainer, $navigationResultContainer, $userGroupHash)
    {
        $this->hashedShopkey = md5(strtoupper($shopkey));
        $this->searchResultContainer = $searchResultContainer;
        $this->navigationResultContainer = $navigationResultContainer;
        $this->userGroupHash = $userGroupHash;
    }
}
