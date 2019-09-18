<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

class Utils
{
    public static function calculateUserGroupHash(string $shopkey, string $customerGroupId): string
    {
        return base64_encode($shopkey ^ $customerGroupId);
    }
}
