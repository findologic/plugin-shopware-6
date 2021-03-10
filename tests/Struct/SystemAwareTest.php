<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\FinSearch\Struct\SystemAware;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class SystemAwareTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSupportsFilterDisabling(): void
    {
        // Filter disabling was introduced in Shopware 6.3.3.0.
        $expectedIsSupported = !Utils::versionLowerThan('6.3.3.0');
        $systemAware = new SystemAware($this->getContainer()->get('router'));

        $this->assertSame($expectedIsSupported, $systemAware->supportsFilterDisabling());
    }
}
