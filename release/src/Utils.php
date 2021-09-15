<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Release;

class Utils
{
    private const COMPOSER_JSON_DEST = __DIR__ . '/../../composer.json';

    public static function getComposerJsonData(): array
    {
        return json_decode(file_get_contents(self::COMPOSER_JSON_DEST), true);
    }

    public static function storeComposerJsonData(array $data): void
    {
        $json = static::toJsonString($data);

        file_put_contents(self::COMPOSER_JSON_DEST, $json);
    }

    public static function toJsonString(
        array $data,
        int $flags = JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
    ): string {
        $rawJson = json_encode($data, $flags) . "\n";

        // Format JSON to two instead of four spaces.
        return preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $rawJson);
    }
}
