<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

class Utils
{
    public static function calculateUserGroupHash(string $shopkey, string $customerGroupId): string
    {
        return base64_encode($shopkey ^ $customerGroupId);
    }

    public static function cleanString(?string $string): ?string
    {
        if (!$string) {
            return null;
        }
        $string = str_replace('\\', '', addslashes(strip_tags($string)));
        $string = str_replace(["\n", "\r", "\t"], ' ', $string);
        // Remove unprintable characters since they would cause an invalid XML.
        $string = self::removeControlCharacters($string);

        return trim($string);
    }

    public static function removeControlCharacters(string $string): string
    {
        $result = preg_replace('/[\x{0000}-\x{001F}]|[\x{007F}]|[\x{0080}-\x{009F}]/u', '', $string);

        return $result ?? $string;
    }
}
