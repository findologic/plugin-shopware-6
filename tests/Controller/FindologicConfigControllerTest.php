<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

use function sprintf;

class FindologicConfigControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function shopkeyProvider(): array
    {
        $salesChannelId = Uuid::randomHex();
        $languageOne = Uuid::randomHex();
        $languageTwo = Uuid::randomHex();

        return [
            'Same shopkey for different languages is provided' => [
                'params' => [
                    sprintf('%s-%s', $salesChannelId, $languageOne) => [
                        'FinSearch.config.shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD'
                    ],
                    sprintf('%s-%s', $salesChannelId, $languageTwo) => [
                        'FinSearch.config.shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD'
                    ]
                ],
                'statusCode' => 500
            ],
            'Unique shopkeys for different languages is provided' => [
                'params' => [
                    sprintf('%s-%s', $salesChannelId, $languageOne) => [
                        'FinSearch.config.shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD'
                    ],
                    sprintf('%s-%s', $salesChannelId, $languageTwo) => [
                        'FinSearch.config.shopkey' => 'FFFFBCDABCDABCDABCDABCDABCDABCD'
                    ]
                ],
                'statusCode' => 204
            ]
        ];
    }

    /**
     * @dataProvider shopkeyProvider
     */
    public function testOnlyUniqueShopkeysCanBeSaved(array $params, int $statusCode): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM finsearch_config');

        $url = '/api/_action/finsearch/batch';
        $client = $this->getBrowser();
        $client->request('POST', $url, $params);

        $this->assertSame($statusCode, $client->getResponse()->getStatusCode());
    }
}
