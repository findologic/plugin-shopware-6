<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use FINDOLOGIC\FinSearch\FinSearch;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class FinSearchTest extends TestCase
{
    public function compatibleVersionsProvider()
    {
        return [
            'Plugin is compatible' => [
                'shopwareVersions' => ['6.1.0', '6.1.6', '6.2.0', '6.2.3', '6.3.0.0', '6.3.3.0', '6.3.5.1'],
                'isCompatible' => true
            ],
            'Plugin is NOT compatible' => [
                'shopwareVersions' => ['6.4.0.0', '6.4.5.0', '6.5.1.2', '6.7.6.4'],
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
