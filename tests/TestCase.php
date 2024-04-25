<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Shopware\Core\Kernel;

class TestCase extends PhpUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Kernel::getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Kernel::getConnection()->rollBack();
    }
}
