<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Config;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\Shopware6Common\Export\Utils\Utils;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;

use function array_key_exists;
use function array_shift;
use function explode;
use function gettype;
use function is_array;

class FindologicConfigService
{
    private array $configs = [];

    public function __construct(
        private readonly EntityRepository $finSearchConfigRepository,
        protected readonly Connection $connection
    ) {
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function get(string $key, string $salesChannelId, string $languageId)
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

    public function getString(string $key, string $salesChannelId, string $languageId): string
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (string)$value;
        }

        throw new InvalidSettingValueException($key, 'string', gettype($value));
    }

    public function getInt(string $key, string $salesChannelId, string $languageId): int
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (int)$value;
        }

        throw new InvalidSettingValueException($key, 'int', gettype($value));
    }

    public function getFloat(string $key, string $salesChannelId, string $languageId): float
    {
        $value = $this->get($key, $salesChannelId, $languageId);
        if (!is_array($value)) {
            return (float)$value;
        }

        throw new InvalidSettingValueException($key, 'float', gettype($value));
    }

    public function getBool(string $key, string $salesChannelId, string $languageId): bool
    {
        return (bool)$this->get($key, $salesChannelId, $languageId);
    }

    public function all(string $salesChannelId, string $languageId): array
    {
        return $this->load($salesChannelId, $languageId);
    }

    /**
     * @throws InvalidDomainException
     * @throws InvalidUuidException
     * @throws InconsistentCriteriaIdsException
     */
    public function getConfig(string $salesChannelId, string $languageId): array
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
    public function set(string $key, $value, string $salesChannelId, string $languageId): void
    {
        $this->configs = [];
        $key = trim($key);
        $this->validate($key, $salesChannelId, $languageId);

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

    public function delete(string $key, string $salesChannel, string $languageId): void
    {
        $this->set($key, null, $salesChannel, $languageId);
    }

    private function validate(string $key, string $salesChannelId, string $languageId): void
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

    private function getId(string $key, string $salesChannelId, string $languageId): ?string
    {
        $criteria = $this->buildCriteria($salesChannelId, $languageId, $key);
        $ids = $this->finSearchConfigRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return array_shift($ids);
    }

    private function buildCriteria(string $salesChannelId, string $languageId, ?string $key = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('languageId', $languageId));

        if ($key) {
            $criteria->addFilter(new EqualsFilter('configurationKey', $key));
        }

        return $criteria;
    }

    private function load(string $salesChannelId, string $languageId): array
    {
        $key = sprintf('%s-%s', $salesChannelId, $languageId);

        if (isset($this->configs[$key])) {
            return $this->configs[$key];
        }

        $criteria = new Criteria();
        if (method_exists($criteria, 'setTitle')) {
            $criteria->setTitle('finsearch-config::load');
        }

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('salesChannelId', $salesChannelId),
                    new EqualsFilter('languageId', $languageId),
                ]
            )
        );

        $criteria->addSorting(
            new FieldSorting('salesChannelId', FieldSorting::ASCENDING),
            new FieldSorting('id', FieldSorting::ASCENDING)
        );
        $criteria->setLimit(500);

        /** @var FinSearchConfigCollection $configs */
        $configs = $this->finSearchConfigRepository->search($criteria, Context::createDefaultContext())->getEntities();
        $this->configs[$key] = $this->parseConfiguration($configs);

        return $this->configs[$key];
    }

    private function buildConfig(FinSearchConfigCollection $configs): array
    {
        $findologicConfig = [];
        foreach ($configs as $config) {
            $keyExists = array_key_exists($config->getConfigurationKey(), $findologicConfig);
            if (!$keyExists || !Utils::isEmpty($config->getConfigurationValue())) {
                $findologicConfig[$config->getConfigurationKey()] = $config->getConfigurationValue();
            }
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
            if (!\array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getConfigValues($configValues[$key], $keys, $value);
        }

        return $configValues;
    }
}
