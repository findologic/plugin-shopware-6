<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Utils;

use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class UtilsTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @return string[]
     */
    public function shopkeyAndCustomerGroupIdProvider(): array
    {
        return [
            'With Shopkey 8D6CA2E49FB7CD09889CC0E2929F86B0' => ['8D6CA2E49FB7CD09889CC0E2929F86B0', '1', 'CQ=='],
            'With Shopkey 0000000000000000000CC0E2929F86B0' => ['0000000000000000000CC0E2929F86B0', '2', 'Ag=='],
            'With Shopkey 000000000000000ZZZZZZZZZZZZZZZZZ' => ['000000000000000ZZZZZZZZZZZZZZZZZ', '3', 'Aw=='],
        ];
    }

    /**
     * @dataProvider shopkeyAndCustomerGroupIdProvider
     */
    public function testCustomerGroupHash(string $shopkey, string $customerGroupId, string $expectedHash): void
    {
        $this->assertSame($expectedHash, Utils::calculateUserGroupHash($shopkey, $customerGroupId));
    }

    public function controlCharacterProvider(): array
    {
        return [
            'Strings with only letters and numbers' => [
                'Findologic123',
                'Findologic123',
                'Expected string to return unchanged'
            ],
            'Strings with whitespace' => [
                ' Findologic123 ',
                ' Findologic123 ',
                'Expected string to not be trimmed'
            ],
            'String with control characters' => [
                "Findologic\n1\t2\r3",
                'Findologic123',
                'Expected control characters to be stripped way'
            ],
            'String with another set of control characters' => [
                "Findologic\xC2\x9F\xC2\x80 Rocks",
                'Findologic Rocks',
                'Expected control characters to be stripped way'
            ],
            'String with special characters' => [
                'Findologic&123',
                'Findologic&123',
                'Expected special characters to be returned as they are'
            ],
            'String with umlauts' => [
                'Findolögic123',
                'Findolögic123',
                'Expected umlauts to be left unaltered.'
            ]
        ];
    }

    /**
     * @dataProvider controlCharacterProvider
     *
     * @param string $text
     * @param string $expected
     * @param string $errorMessage
     */
    public function testControlCharacterMethod($text, $expected, $errorMessage): void
    {
        $result = Utils::removeControlCharacters($text);
        $this->assertEquals($expected, $result, $errorMessage);
    }

    public static function cleanStringProvider(): array
    {
        return [
            'String with HTML tags' => [
                '<span>Findologic Rocks</span>',
                'Findologic Rocks',
                'Expected HTML tags to be stripped away'
            ],
            'String with single quotes' => [
                "Findologic's team rocks",
                'Findologic\'s team rocks',
                'Expected single quotes to be escaped with back slash'
            ],
            'String with double quotes' => [
                'Findologic "Rocks!"',
                'Findologic "Rocks!"',
                'Expected double quotes to be escaped with back slash'
            ],
            'String with back slashes' => [
                "Findologic\ Rocks!\\",
                'Findologic Rocks!',
                'Expected back slashes to be stripped away'
            ],
            'String with preceding space' => [
                ' Findologic Rocks ',
                'Findologic Rocks',
                'Expected preceding and succeeding spaces to be stripped away'
            ],
            'Strings with only letters and numbers' => [
                'Findologic123',
                'Findologic123',
                'Expected string to return unchanged'
            ],
            'String with control characters' => [
                "Findologic\n1\t2\r3",
                'Findologic 1 2 3',
                'Expected control characters to be stripped way'
            ],
            'String with another set of control characters' => [
                "Findologic\xC2\x9F\xC2\x80 Rocks",
                'Findologic Rocks',
                'Expected control characters to be stripped way'
            ],
            'String with special characters' => [
                'Findologic&123!',
                'Findologic&123!',
                'Expected special characters to be returned as they are'
            ],
            'String with umlauts' => [
                'Findolögic123',
                'Findolögic123',
                'Expected umlauts to be left unaltered.'
            ]
        ];
    }

    /**
     * @dataProvider cleanStringProvider
     *
     * @param string $text
     * @param string $expected
     * @param string $errorMessage
     */
    public function testCleanStringMethod($text, $expected, $errorMessage): void
    {
        $result = Utils::cleanString($text);
        $this->assertEquals($expected, $result, $errorMessage);
    }

    public function findologicActiveProvider(): array
    {
        return [
            'Plugin is inactive' => [
                'isActive' => false,
                'isActiveOnCategory' => false,
                'isDirectIntegration' => false,
                'isStagingShop' => false,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => false,
            ],
            'Plugin is active' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => false,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => true,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active but staging is enabled' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => true,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => false,
            ],
            'Plugin is active but staging is enabled and findologic=on is set' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => true,
                'isStagingSession' => true,
                'isCategoryPage' => false,
                'expectedFindologicActive' => true,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active and direct integration' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => true,
                'isStagingShop' => false,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active and direct integration with staging' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => true,
                'isStagingShop' => true,
                'isStagingSession' => true,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active and direct integration with staging but without findologic=on' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => true,
                'isStagingShop' => true,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => true, // Smart Suggest handles findologic=on in case of DI.
            ],
            'Plugin is active and with staging on category page' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => true,
                'isStagingSession' => true,
                'isCategoryPage' => false,
                'expectedFindologicActive' => true,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active and with staging on category page but setting is inactive' => [
                'isActive' => true,
                'isActiveOnCategory' => false,
                'isDirectIntegration' => false,
                'isStagingShop' => true,
                'isStagingSession' => true,
                'isCategoryPage' => true,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is inactive and user is on category page' => [
                'isActive' => false,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => false,
                'isStagingSession' => false,
                'isCategoryPage' => true,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => false,
            ],
        ];
    }

    /**
     * @dataProvider findologicActiveProvider
     */
    public function testFindologicActive(
        bool $isActive,
        bool $isActiveOnCategory,
        bool $isDirectIntegration,
        bool $isStagingShop,
        bool $isStagingSession,
        bool $isCategoryPage,
        bool $expectedFindologicActive,
        bool $expectedSmartSuggestActive
    ): void {
        /** @var Session|MockObject $sessionMock */
        $sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request($isStagingSession ? ['findologic' => 'on'] : []);
        $request->setSession($sessionMock);
        $context = Context::createDefaultContext();
        /** @var ServiceConfigResource|MockObject $serviceConfigResourceMock */
        $serviceConfigResourceMock = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $serviceConfigResourceMock->expects($this->any())->method('isDirectIntegration')
            ->willReturn($isDirectIntegration);
        $serviceConfigResourceMock->expects($this->any())->method('isStaging')
            ->willReturn($isStagingShop);
        /** @var Config|MockObject $configMock */
        $configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configMock->expects($this->any())->method('isInitialized')->willReturn(true);
        $configMock->expects($this->any())->method('isActive')->willReturn($isActive);
        $configMock->expects($this->any())->method('isActiveOnCategoryPages')
            ->willReturn($isActiveOnCategory);

        $shouldHandleRequest = Utils::shouldHandleRequest(
            $request,
            $context,
            $serviceConfigResourceMock,
            $configMock,
            $isCategoryPage
        );

        /** @var FindologicService $findologicService */
        $findologicService = $context->getExtension('findologicService');

        $this->assertSame($expectedFindologicActive, $shouldHandleRequest);
        $this->assertSame($expectedFindologicActive, $findologicService->getEnabled());
        $this->assertSame($expectedSmartSuggestActive, $findologicService->getSmartSuggestEnabled());
    }

    public function categoryProvider(): array
    {
        return [
            'main category' => [
                'breadCrumbs' => ['Main'],
                'expectedCategoryPath' => '',
            ],
            'one subcategory' => [
                'breadCrumbs' => ['Main', 'Sub'],
                'expectedCategoryPath' => 'Sub',
            ],
            'two subcategories' => [
                'breadCrumbs' => ['Main', 'Sub', 'SubOfSub'],
                'expectedCategoryPath' => 'Sub_SubOfSub',
            ],
            'three subcategories' => [
                'breadCrumbs' => ['Main', 'Sub', 'SubOfSub', 'very deep'],
                'expectedCategoryPath' => 'Sub_SubOfSub_very deep',
            ],
            'three subcategories with redundant spaces' => [
                'breadCrumbs' => [' Main', ' Sub ', 'SubOfSub  ', '   very deep'],
                'expectedCategoryPath' => 'Sub_SubOfSub_very deep',
            ],
        ];
    }

    /**
     * @dataProvider categoryProvider
     */
    public function testCategoryPathIsProperlyBuilt(array $breadCrumbs, string $expectedCategoryPath): void
    {
        $categoryPath = Utils::buildCategoryPath($breadCrumbs);

        $this->assertSame($expectedCategoryPath, $categoryPath);
    }
}
