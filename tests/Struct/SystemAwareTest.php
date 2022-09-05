<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\FinSearch\Struct\SystemAware;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class SystemAwareTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSupportsFilterDisabling(): void
    {
        $systemAware = new SystemAware($this->getContainer()->get('router'));

        $this->assertTrue($systemAware->supportsFilterDisabling());
    }
}
