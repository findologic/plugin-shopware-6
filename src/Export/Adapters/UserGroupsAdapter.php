<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Usergroup;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;

class UserGroupsAdapter
{
    private ExportContext $exportContext;

    public function __construct(ExportContext $exportContext)
    {
        $this->exportContext = $exportContext;
    }

    /**
     * @return Usergroup[]
     */
    public function adapt(ProductEntity $product): array
    {
        $userGroups = [];

        /** @var CustomerGroupEntity $customerGroupEntity */
        foreach ($this->exportContext->getCustomerGroups() as $customerGroupEntity) {
            $userGroups[] = new Usergroup(
                Utils::calculateUserGroupHash($this->exportContext->getShopkey(), $customerGroupEntity->getId())
            );
        }

        return $userGroups;
    }
}
