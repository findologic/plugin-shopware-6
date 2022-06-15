<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Helper;

class StaticHelper
{
    public static function legacyExtensionInstalled(): bool
    {
        if (
            class_exists('FINDOLOGIC\FinSearch\Export\FindologicProductFactory') &&
            class_exists('FINDOLOGIC\FinSearch\Export\ProductService')
        ) {
            return true;
        }

        return false;
    }
}
