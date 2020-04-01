<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

trait MockResponseHelper
{
    protected function getMockResponse(string $path = 'XMLResponse/demo.xml'): string
    {
        return file_get_contents(__DIR__ . '/../../MockData/' . $path);
    }
}
