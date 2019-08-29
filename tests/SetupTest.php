<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearchTests;

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use PHPUnit\Framework\TestCase;

class SetupTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testWorks(): void
    {
        $this->assertTrue(true);
    }
}
