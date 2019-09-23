<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearchTests\Utils;

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
     *
     * @param string $shopkey
     * @param string $customerGroupId
     * @param string $expectedHash
     */
    public function testCustomerGroupHash(string $shopkey, string $customerGroupId, string $expectedHash): void
    {
        $this->assertSame($expectedHash, Utils::calculateUserGroupHash($shopkey, $customerGroupId));
    }
}
