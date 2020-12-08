<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1607322344AllowNullSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607322344;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `finsearch_config`
CHANGE `sales_channel_id` `sales_channel_id` binary(16) NULL AFTER `configuration_value`,
CHANGE `language_id` `language_id` binary(16) NULL AFTER `sales_channel_id`
SQL;

        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
