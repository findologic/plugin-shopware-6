<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Migration;

use Doctrine\DBAL\Connection;
use Exception;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1605714035FinSearchConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1605714035;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `finsearch_config` (
  `id` binary(16) NOT NULL,
  `configuration_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `configuration_value` json NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.finsearch_config.key` (`configuration_key`,`sales_channel_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        if (method_exists($connection, 'executeStatement')) {
            $connection->executeStatement($sql);
        } else {
            $connection->executeUpdate($sql);
        }

        $this->insertPreviousConfigurationIfExists($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * Custom Findologic configuration is implemented after v1.3.2, so we need to properly migrate any existing config
     * from SystemConfig to FindologicConfig
     */
    private function insertPreviousConfigurationIfExists(Connection $connection): void
    {
        $sql = <<<SQL
SELECT LOWER(HEX(`id`)) AS `id`, 
configuration_key, configuration_value, 
LOWER(HEX(`sales_channel_id`)) AS `sales_channel_id`, 
created_at, updated_at
FROM `system_config` 
WHERE `configuration_key` LIKE '%FinSearch.config%'
SQL;

        if (method_exists($connection, 'fetchAllAssociative')) {
            $configs = $connection->fetchAllAssociative($sql);
        } else {
            $configs = $connection->fetchAll($sql);
        }

        if (Utils::isEmpty($configs)) {
            return;
        }

        foreach ($configs as $config) {
            $salesChannelId = $config['sales_channel_id'];
            if (!$salesChannelId) {
                $salesChannelId = $this->getDefaultSalesChannelId($connection);
            }

            $sql = 'SELECT LOWER(HEX(`language_id`)) AS `language_id` FROM `sales_channel` WHERE `id` = UNHEX(?)';
            if (method_exists($connection, 'fetchOne')) {
                $languageId = $connection->fetchOne($sql, [$salesChannelId]);
            } else {
                $languageId = $connection->fetchColumn($sql, [$salesChannelId]);
            }

            if (!$languageId) {
                continue;
            }

            $data = $config;
            $data['language_id'] = Uuid::fromHexToBytes($languageId);
            $data['sales_channel_id'] = Uuid::fromHexToBytes($salesChannelId);
            try {
                $connection->insert('finsearch_config', $data);
            } catch (Exception $ignored) {
                // Do nothing here as configuration already exists
            }
        }
    }

    private function getDefaultSalesChannelId(Connection $connection)
    {
        $sql = 'SELECT LOWER(HEX(`id`)) AS `id` FROM `sales_channel` WHERE `type_id` = UNHEX(?) AND `active` = \'1\'';

        if (method_exists($connection, 'fetchOne')) {
            return $connection->fetchOne($sql, [Defaults::SALES_CHANNEL_TYPE_STOREFRONT]);
        }

        return $connection->fetchColumn($sql, [Defaults::SALES_CHANNEL_TYPE_STOREFRONT]);
    }
}
