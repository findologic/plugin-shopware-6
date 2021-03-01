<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Exceptions;

class PluginNotCompatibleException extends FindologicException
{
    public function __construct()
    {
        parent::__construct('This plugin is not compatible with the used Shopware version');
    }
}
