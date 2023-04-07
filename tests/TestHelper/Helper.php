<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\TestHelper;

use ReflectionClass;

class Helper
{
    public static function callMethod($obj, $name, array $args)
    {
        $class = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        return $method->invokeArgs($obj, $args);
    }
}
