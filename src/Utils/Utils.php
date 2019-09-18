<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

class Utils
{
    /**
     * @param string $shopkey
     * @param string $customerGroupId
     *
     * @return string
     */
    public static function calculateUserGroupHash($shopkey, $customerGroupId)
    {
        return base64_encode($shopkey ^ $customerGroupId);
    }
}
