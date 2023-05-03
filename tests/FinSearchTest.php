<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use FINDOLOGIC\FinSearch\FinSearch;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class FinSearchTest extends TestCase
{
    public static function compatibleVersionsProvider(): array
    {
        return [
            'Plugin is compatible' => [
                'shopwareVersions' => ['6.4.6', '6.4.10', '6.4.99.99'],
                'isCompatible' => true
            ],
            'Plugin is NOT compatible' => [
                'shopwareVersions' => ['6.4.0.0', '6.4.5.0', '6.5.0-rc.1', '6.5.1.2', '6.7.6.4'],
                'isCompatible' => false
            ],
        ];
    }

    /**
     * @dataProvider compatibleVersionsProvider
     */
    public function testIfPluginIsCompatibleWithShopwareVersion(array $shopwareVersions, bool $isCompatible)
    {
        $composerJsonPath = __DIR__ . '/MockData/ComposerJson/demo_composer.json';
        $installContext = $this->getMockBuilder(InstallContext::class)->disableOriginalConstructor()->getMock();
        foreach ($shopwareVersions as $version) {
            $installContext->method('getCurrentShopwareVersion')->willReturn($version);
            $status = FinSearch::isCompatible($installContext, $composerJsonPath);
            $this->assertSame($isCompatible, $status);
        }
    }

    /**
     * @dataProvider compatibleVersionsProvider
     */
    public function testPluginIsAlwaysCompatibleOnDev(array $shopwareVersions)
    {
        $installContext = $this->getMockBuilder(InstallContext::class)->disableOriginalConstructor()->getMock();
        foreach ($shopwareVersions as $version) {
            $installContext->method('getCurrentShopwareVersion')->willReturn($version);
            $status = FinSearch::isCompatible($installContext);
            $this->assertTrue($status);
        }
    }
}
