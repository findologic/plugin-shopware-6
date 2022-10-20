<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Config;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;

use function array_key_exists;
use function array_shift;
use function explode;
use function gettype;
use function is_array;
use function is_bool;

class FindologicConfigService
{
    public const CONFIG_KEYS = [
        'FinSearch.config.shopkey',
        'FinSearch.config.active',
        'FinSearch.config.activeOnCategoryPages',
        'FinSearch.config.crossSellingCategories',
        'FinSearch.config.searchResultContainer',
        'FinSearch.config.navigationResultContainer',
        'FinSearch.config.integrationType',
        'FinSearch.config.filterPosition',
    ];

    public const REQUIRED_CONFIG_KEYS = [
        'FinSearch.config.shopkey'
    ];

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $finSearchConfigRepository;

    /**
     * @var array[]
     */
    private $configs = [];

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(
        EntityRepositoryInterface $finSearchConfigRepository,
        Connection $connection,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->finSearchConfigRepository = $finSearchConfigRepository;
        $this->connection = $connection;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function get(string $key, ?string $salesChannelId = null, ?string $languageId = null)
    {
        $config = $this->load($salesChannelId, $languageId);
        $parts = explode('.', $key);
        $pointer = $config;

        foreach ($parts as $part) {
            if (!is_array($pointer)) {
                return null;
            }

            if (array_key_exists($part, $pointer)) {
                $pointer = $pointer[$part];
                continue;
            }

            return null;
        }

        return $pointer;
    }

    public function getString(string $key, ?string $salesChannelId = null, ?string $languageId = null): string
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (string)$value;
        }

        throw new InvalidSettingValueException($key, 'string', gettype($value));
    }

    public function getInt(string $key, ?string $salesChannelId = null, ?string $languageId = null): int
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (int)$value;
        }

        throw new InvalidSettingValueException($key, 'int', gettype($value));
    }

    public function getFloat(string $key, ?string $salesChannelId = null, ?string $languageId = null): float
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (float)$value;
        }

        throw new InvalidSettingValueException($key, 'float', gettype($value));
    }

    public function getBool(string $key, ?string $salesChannelId = null, ?string $languageId = null): bool
    {
        return (bool)$this->get($key, $salesChannelId, $languageId);
    }

    public function all(?string $salesChannelId = null, ?string $languageId = null): array
    {
        return $this->load($salesChannelId, $languageId);
    }

    /**
     * @throws InvalidDomainException
     * @throws InvalidUuidException
     * @throws InconsistentCriteriaIdsException
     */
    public function getConfig(?string $salesChannelId = null, ?string $languageId = null): array
    {
        $criteria = $this->buildCriteria($salesChannelId, $languageId);

        /** @var FinSearchConfigCollection $collection */
        $collection = $this->finSearchConfigRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        return $this->buildConfig($collection);
    }

    /**
     * @param array|bool|float|int|string|null $value
     */
    public function set(string $key, $value, ?string $salesChannelId = null, ?string $languageId = null): void
    {
        $this->configs = [];
        $key = trim($key);
        $this->validate($key, $salesChannelId, $languageId);

        // If no sales channel is given, we have to manually set it for each language of each sales channel.
        if ($salesChannelId === null) {
            // Required configuration must have a sales channel so we skip them in this scenario.
            if (in_array($key, self::REQUIRED_CONFIG_KEYS, false)) {
                return;
            }
            $this->setConfig($key, $salesChannelId, $languageId, $value);
            $salesChannels = $this->getAllSalesChannels();
            foreach ($salesChannels as $salesChannelEntity) {
                /** @var LanguageEntity $language */
                foreach ($salesChannelEntity->getLanguages() as $language) {
                    $this->setConfig($key, $salesChannelEntity->getId(), $language->getId(), $value);
                }
            }
        }

        $this->setConfig($key, $salesChannelId, $languageId, $value);
    }

    public function delete(string $key, ?string $salesChannel, ?string $languageId): void
    {
        $this->set($key, null, $salesChannel, $languageId);
    }

    private function validate(string $key, ?string $salesChannelId = null, ?string $languageId = null): void
    {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidKeyException('Key cannot be empty');
        }
        if ($salesChannelId && !Uuid::isValid($salesChannelId)) {
            throw new InvalidUuidException($salesChannelId);
        }
        if ($languageId && !Uuid::isValid($languageId)) {
            throw new InvalidUuidException($languageId);
        }
    }

    private function getId(string $key, ?string $salesChannelId = null, ?string $languageId = null): ?string
    {
        $criteria = $this->buildCriteria($salesChannelId, $languageId, $key);
        $ids = $this->finSearchConfigRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return array_shift($ids);
    }

    private function buildCriteria(
        ?string $salesChannelId = null,
        ?string $languageId = null,
        ?string $key = null
    ): Criteria {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [new EqualsFilter('salesChannelId', $salesChannelId), new EqualsFilter('salesChannelId', null)]
            )
        );
        $criteria->addFilter(new EqualsFilter('languageId', $languageId));
        $criteria->addSorting(
            new FieldSorting('salesChannelId', FieldSorting::ASCENDING),
            new FieldSorting('id', FieldSorting::ASCENDING)
        );

        if ($key) {
            $criteria->addFilter(new EqualsFilter('configurationKey', $key));
        }

        return $criteria;
    }

    private function load(?string $salesChannelId = null, ?string $languageId = null): array
    {
        if ($languageId) {
            $key = sprintf('%s-%s', $salesChannelId, $languageId);
        } else {
            $key = 'global';
        }

        if (isset($this->configs[$key])) {
            return $this->configs[$key];
        }

        $criteria = $this->buildCriteria($salesChannelId, $languageId);
        $criteria->setLimit(500);

        /** @var FinSearchConfigCollection $configs */
        $configs = $this->finSearchConfigRepository->search($criteria, Context::createDefaultContext())->getEntities();
        $this->configs[$key] = $this->parseConfiguration($configs);

        return $this->configs[$key];
    }

    private function buildConfig(FinSearchConfigCollection $options): array
    {
        $findologicConfig = [];
        // Set the configuration schema for enabling inheritance
        foreach (self::CONFIG_KEYS as $configKey) {
            $findologicConfig[$configKey] = null;
        }

        foreach ($options as $config) {
            $value = $config->getConfigurationValue();
            $key = $config->getConfigurationKey();
            if (Utils::isEmpty($value)) {
                continue;
            }

            $findologicConfig[$key] = $value;
        }

        return $findologicConfig;
    }

    /**
     * The keys of the configs look like `FinSearch.config.shopkey`.
     * This method splits those strings and builds an array structure
     * ```
     * Array
     * (
     *     [FinSearch] => Array
     *         (
     *             [config] => Array
     *                 (
     *                     [shopkey] => 'someValue'
     *                 )
     *         )
     * )
     * ```
     */
    private function parseConfiguration(FinSearchConfigCollection $collection): array
    {
        $configValues = [];

        foreach ($collection as $config) {
            $keys = explode('.', $config->getConfigurationKey());

            $configValues = $this->getConfigValues($configValues, $keys, $config->getConfigurationValue());
        }

        return $configValues;
    }

    private function getConfigValues(array $configValues, array $keys, $value): array
    {
        $key = array_shift($keys);

        if (empty($keys)) {
            $configValues[$key] = $value;
        } else {
            if (!array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getConfigValues($configValues[$key], $keys, $value);
        }

        return $configValues;
    }

    private function getAllSalesChannels(): SalesChannelCollection
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAssociation('languages');

        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        return $salesChannels;
    }

    /**
     * @param string $key
     * @param string|null $salesChannelId
     * @param string|null $languageId
     * @param mixed|null $value
     */
    private function setConfig(
        string $key,
        ?string $salesChannelId = null,
        ?string $languageId = null,
        $value = null
    ): void {
        $id = $this->getId($key, $salesChannelId, $languageId);
        if ($value === null) {
            if ($id) {
                $this->finSearchConfigRepository->delete([['id' => $id]], Context::createDefaultContext());
            }

            return;
        }

        $this->finSearchConfigRepository->upsert(
            [
                [
                    'id' => $id ?? Uuid::randomHex(),
                    'configurationKey' => $key,
                    'configurationValue' => $value,
                    'salesChannelId' => $salesChannelId,
                    'languageId' => $languageId,
                ]
            ],
            Context::createDefaultContext()
        );
    }
}
