<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

class Utils
{
    /**
     * @param string $shopkey
     * @param string $userGroupId
     *
     * @return string
     */
    public static function calculateUserGroupHash($shopkey, $userGroupId)
    {
        return base64_encode($shopkey ^ $userGroupId);
    }
}
