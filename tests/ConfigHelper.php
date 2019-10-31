<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

trait ConfigHelper
{
    public function getShopkey(): string
    {
        return '74B87337454200D4D33F80C4663DC5E5';
    }

    public function getConfig(bool $assoc = true)
    {
        $config = file_get_contents(__DIR__ . '/example_config.json');
        if ($assoc) {
            return json_decode($config, true);
        }

        return $config;
    }
}
