<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait SalesChannelHelper
{
    public function buildSalesChannelContext(): SalesChannelContext
    {
        $id = Uuid::randomHex();
        $salesChannel = [
            'id' => $id,
            'name' => 'test',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->fetchIdFromDatabase('payment_method'),
            'shippingMethodId' => $this->fetchIdFromDatabase('shipping_method'),
            'countryId' => $this->fetchIdFromDatabase('country'),
            'navigationCategoryId' => $this->fetchIdFromDatabase('category'),
            'accessKey' => 'test',
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->fetchIdFromDatabase('snippet_set'),
                ],
            ],
        ];

        $this->getContainer()->get('sales_channel.repository')->create(
            [$salesChannel],
            Context::createDefaultContext()
        );

        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), $id);
    }

    /**
     * In order to create a useable sales channel context we need to pass some IDs for initialization from several
     * tables from the database.
     */
    public function fetchIdFromDatabase(string $table): string
    {
        return $this->getContainer()->get(Connection::class)->fetchColumn('SELECT LOWER(HEX(id)) FROM ' . $table);
    }
}
