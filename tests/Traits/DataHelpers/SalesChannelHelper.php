<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
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
        string $url = 'http://test.uk',
        ?CustomerEntity $customerEntity = null,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
        array $overrides = [],
        string $currencyId = Defaults::CURRENCY
    ): SalesChannelContext {
        $locale = $this->getLocaleOfLanguage($languageId);
        if ($locale) {
            $snippetSet = $this->getSnippetSetIdForLocale($locale);
        } else {
            $snippetSet = $this->fetchIdFromDatabase('snippet_set');
        }
        $salesChannel = array_merge([
            'id' => $salesChannelId,
            'languageId' => $languageId,
            'domains' => [
                [
                    'url' => $url,
                    'currencyId' => $currencyId,
                    'languageId' => $languageId,
                    'snippetSetId' => $snippetSet
                ]
            ],
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        ], $overrides);

        $this->getContainer()->get('sales_channel.repository')->upsert(
            [$salesChannel],
            Context::createDefaultContext()
        );

        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $this->buildSalesChannelContextFactoryOptions($customerEntity, $languageId, $currencyId)
        );
    }

    private function buildSalesChannelContextFactoryOptions(
        ?CustomerEntity $customerEntity,
        ?string $languageId,
        ?string $currencyId
    ): array {
        $options = [];
        if ($customerEntity) {
            $options[SalesChannelContextService::CUSTOMER_ID] = $customerEntity->getId();
        }
        if ($languageId) {
            $options[SalesChannelContextService::LANGUAGE_ID] = $languageId;
        }
        if ($currencyId) {
            $options[SalesChannelContextService::CURRENCY_ID] = $currencyId;
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

    protected function getLocaleIdOfLanguage(string $languageId = Defaults::LANGUAGE_SYSTEM): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('language.repository');

        /** @var LanguageEntity $language */
        $language = $repository->search(new Criteria([$languageId]), Context::createDefaultContext())->get($languageId);

        return $language->getLocaleId();
    }

    public function getLocaleOfLanguage(string $languageId = Defaults::LANGUAGE_SYSTEM): ?string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('language.repository');

        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('translationCode');

        /** @var LanguageEntity $language */
        $language = $repository->search($criteria, Context::createDefaultContext())->get($languageId);

        return $language->getTranslationCode() ? $language->getTranslationCode()->getCode() : null;
    }

    public function createLanguage(string $id, ?string $parentId = Defaults::LANGUAGE_SYSTEM): void
    {
        /* @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');

        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfLanguage(),
                    'parentId' => $parentId,
                    'salesChannels' => [
                        ['id' => Defaults::SALES_CHANNEL],
                    ]
                ],
            ],
            Context::createDefaultContext()
        );
    }

    public function getEnGbLanguageId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('language.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.translationCode.code', 'en-GB'));

        /** @var LanguageEntity $language */
        $language = $repository->search($criteria, Context::createDefaultContext())->first();

        return $language->getId();
    }

    public function createCurrency(array $data = []): string
    {
        $currencyId = Uuid::randomHex();

        $cashRoundingConfig = [
            'decimals' => 2,
            'interval' => 1,
            'roundForNet' => false
        ];

        /** @var EntityRepositoryInterface $currencyRepo */
        $currencyRepo = $this->getContainer()->get('currency.repository');
        $currencyRepo->upsert(
            [
                array_merge([
                    'id' => $currencyId,
                    'isoCode' => 'FDL',
                    'factor' => 1,
                    'symbol' => 'F',
                    'decimalPrecision' => 2,
                    'name' => 'Findologic Currency',
                    'shortName' => 'FL',
                    'itemRounding' => $cashRoundingConfig,
                    'totalRounding' => $cashRoundingConfig,
                ], $data)
            ],
            Context::createDefaultContext()
        );

        return $currencyId;
    }
}
