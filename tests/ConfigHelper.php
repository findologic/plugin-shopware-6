<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

trait ConfigHelper
{
    public function getShopkey(): string
    {
        return '80AB18D4BE2654E78244106AD315DC2C';
    }

    public function getConfig(?bool $assoc = true)
    {
        $config = file_get_contents(__DIR__ . '/example_config.json');
        if ($assoc) {
            return json_decode($config, true);
        }

        return $config;
    }
}
