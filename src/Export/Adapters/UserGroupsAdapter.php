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
    /**
     * @return Usergroup[]
     */
    public function adapt(ProductEntity $product, ExportContext $exportContext): array
    {
        $userGroups = [];

        /** @var CustomerGroupEntity $customerGroupEntity */
        foreach ($exportContext->getCustomerGroups() as $customerGroupEntity) {
            $userGroups[] = new Usergroup(
                Utils::calculateUserGroupHash($exportContext->getShopkey(), $customerGroupEntity->getId())
            );
        }

        return $userGroups;
    }
}
