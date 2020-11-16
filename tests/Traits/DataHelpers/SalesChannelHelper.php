<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @method ContainerInterface getContainer()
 */
trait SalesChannelHelper
{
    public function buildSalesChannelContext(
        string $salesChannelId = Defaults::SALES_CHANNEL,
        string $url = 'http://test.de',
        ?CustomerEntity $customerEntity = null
    ): SalesChannelContext {
        $salesChannel = [
            'id' => $salesChannelId,
            'domains' => [
                [
                    'url' => $url,
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->fetchIdFromDatabase('snippet_set'),
                ]
            ]
        ];

        $this->getContainer()->get('sales_channel.repository')->update(
            [$salesChannel],
            Context::createDefaultContext()
        );

        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $this->buildSalesChannelContextFactoryOptions($customerEntity)
        );
    }

    private function buildSalesChannelContextFactoryOptions(?CustomerEntity $customerEntity): array
    {
        $options = [];
        if ($customerEntity) {
            $options[SalesChannelContextService::CUSTOMER_ID] = $customerEntity->getId();
        }

        return $options;
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
