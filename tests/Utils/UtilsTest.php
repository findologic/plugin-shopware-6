<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Utils;

use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

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
                'Findologic123',
                'Expected string to be trimmed'
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
}
