<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

trait ConfigHelper
{
    public function getShopkey(): string
    {
        return '74B87337454200D4D33F80C4663DC5E5';
    }

    public function getConfig(bool $assoc = true)
    {
        $config = file_get_contents(__DIR__ . '/example_config.json');
        if ($assoc) {
            return json_decode($config, true);
        }

        return $config;
    }

    public function getDemoXMLResponse(): string
    {
        return file_get_contents(__DIR__ . '/demo.xml');
    }

    /**
     * Creates a system config service mock with default findologic config values initialized
     * Passing the data array will override any default values if needed
     */
    private function getDefaultFindologicConfigServiceMock(
        TestCase $testClass,
        array $overrides = []
    ): SystemConfigService {
        /** @var SystemConfigService|MockObject $configServiceMock */
        $configServiceMock = $testClass->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $defaultConfig = [
            'active' => true,
            'shopkey' => $this->getShopkey(),
            'activeOnCategoryPages' => true,
            'searchResultContainer' => 'fl-result',
            'navigationResultContainer' => 'fl-navigation-result',
            'integrationType' => 'Direct Integration',
        ];

        $config = array_merge($defaultConfig, $overrides);

        $configServiceMock->method('get')
            ->willReturnOnConsecutiveCalls(
                $config['active'],
                $config['shopkey'],
                $config['activeOnCategoryPages'],
                $config['searchResultContainer'],
                $config['navigationResultContainer'],
                $config['integrationType']
            );

        return $configServiceMock;
    }

    public function createAndGetSalesChannelContext(): SalesChannelContext
    {
        $id = Uuid::randomHex();
        $salesChannel = [
            'id' => $id,
            'name' => 'test',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getRandomId('payment_method'),
            'shippingMethodId' => $this->getRandomId('shipping_method'),
            'countryId' => $this->getRandomId('country'),
            'navigationCategoryId' => $this->getRandomId('category'),
            'accessKey' => 'test',
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ];

        $this->getContainer()->get('sales_channel.repository')
            ->create([$salesChannel], Context::createDefaultContext());

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), $id);
    }

    private function getRandomId(string $table)
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT LOWER(HEX(id)) FROM ' . $table);
    }
}
