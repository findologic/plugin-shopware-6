<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait SalesChannelHelper
{
    public function buildSalesChannelContext(
        string $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
        ?CustomerEntity $customerEntity = null,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
    ): SalesChannelContext {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $this->buildSalesChannelContextFactoryOptions($customerEntity, $languageId)
        );
    }

    public function buildAndCreateSalesChannelContext(
        string $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
        string $url = 'http://test.uk',
        ?CustomerEntity $customerEntity = null,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
        array $overrides = [],
        string $currencyId = Defaults::CURRENCY
    ): SalesChannelContext {
        $this->upsertSalesChannel($salesChannelId, $url, $customerEntity, $languageId, $overrides, $currencyId);

        return $this->buildSalesChannelContext($salesChannelId, $customerEntity, $languageId);
    }

    public function upsertSalesChannel(
        string $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
        string $url = 'http://test.uk',
        ?CustomerEntity $customerEntity = null,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
        array $overrides = [],
        string $currencyId = Defaults::CURRENCY
    ): void {
        $locale = $this->getLocaleOfLanguage($languageId);

        if ($locale) {
            $snippetSet = $this->getSnippetSetIdForLocale($locale);
        } else {
            $snippetSet = $this->fetchIdFromDatabase('snippet_set');
        }

        $countryId = $this->getContainer()->get('country.repository')->searchIds(
            new Criteria(),
            Context::createDefaultContext()
        )->firstId();

        $paymentMethodId = $this->getContainer()->get('payment_method.repository')->searchIds(
            new Criteria(),
            Context::createDefaultContext()
        )->firstId();

        $shippingMethodId = $this->getContainer()->get('shipping_method.repository')->searchIds(
            new Criteria(),
            Context::createDefaultContext()
        )->firstId();

        $customerGroupId = $this->getContainer()->get('customer_group.repository')->searchIds(
            new Criteria(),
            Context::createDefaultContext()
        )->firstId();

        $catCriteria = new Criteria();
        $catCriteria->addFilter(
            new EqualsFilter('parentId', null)
        );
        $navigationCategoryId = $this->getContainer()->get('category.repository')->searchIds(
            $catCriteria,
            Context::createDefaultContext()
        )->firstId();

        $salesChannel = array_merge([
            'id' => $salesChannelId,
            'languageId' => $languageId,
            'customerGroupId' => $customerEntity?->getGroupId() ?? $customerGroupId,
            'currencyId' => $currencyId,
            'paymentMethodId' => $paymentMethodId,
            'shippingMethodId' => $shippingMethodId,
            'countryId' => $countryId,
            'navigationCategoryId' => $navigationCategoryId,
            'accessKey' => 'KEY',
            'domains' => [
                [
                    'url' => $url,
                    'currencyId' => $currencyId,
                    'languageId' => $languageId,
                    'snippetSetId' => $snippetSet
                ]
            ],
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'translations' => [
                $languageId => [
                    'name' => 'Storefront'
                ]
            ],
            'languages' => [
                ['id' => $languageId]
            ]
        ], $overrides);

        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $salesChannelExists = $salesChannelRepository
            ->searchIds(new Criteria([$salesChannelId]), Context::createDefaultContext())
            ->firstId();

        if ($languageId !== Defaults::LANGUAGE_SYSTEM) {
            $salesChannel['translations'][Defaults::LANGUAGE_SYSTEM] = ['name' => 'Storefront Default'];
        }

        if ($salesChannelExists && $languageId === Defaults::LANGUAGE_SYSTEM) {
            unset($salesChannel['languages']);
        }

        $salesChannelRepository->upsert(
            [$salesChannel],
            Context::createDefaultContext()
        );
    }

    private function buildSalesChannelContextFactoryOptions(
        ?CustomerEntity $customerEntity,
        ?string $languageId
    ): array {
        $options = [];
        if ($customerEntity) {
            $options[SalesChannelContextService::CUSTOMER_ID] = $customerEntity->getId();
        }
        if ($languageId) {
            $options[SalesChannelContextService::LANGUAGE_ID] = $languageId;
        }

        return $options;
    }

    /**
     * In order to create a useable sales channel context we need to pass some IDs for initialization from several
     * tables from the database.
     */
    public function fetchIdFromDatabase(string $table): string
    {
        return $this->getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM ' . $table);
    }

    protected function getLocaleIdOfLanguage(string $languageId = Defaults::LANGUAGE_SYSTEM): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('language.repository');

        /** @var LanguageEntity $language */
        $language = $repository->search(new Criteria([$languageId]), Context::createDefaultContext())->get($languageId);

        return $language->getLocaleId();
    }

    public function getLocaleOfLanguage(string $languageId = Defaults::LANGUAGE_SYSTEM): ?string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('language.repository');

        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('translationCode');

        /** @var LanguageEntity $language */
        $language = $repository->search($criteria, Context::createDefaultContext())->get($languageId);

        return $language->getTranslationCode()?->getCode();
    }

    public function createLanguage(string $id, ?string $parentId = Defaults::LANGUAGE_SYSTEM): void
    {
        /* @var EntityRepository $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');

        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfLanguage(),
                    'parentId' => $parentId,
                ],
            ],
            Context::createDefaultContext()
        );
    }

    public function getEnGbLanguageId(): string
    {
        /** @var EntityRepository $repository */
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

        /** @var EntityRepository $currencyRepo */
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
