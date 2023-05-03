<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

trait RandomIdHelper
{
    public function fetchFirstIdFromTable(string $table): string
    {
        $connection = $this->getContainer()->get(Connection::class);

        return Uuid::fromBytesToHex((string)$connection->fetchOne("SELECT id FROM {$table} LIMIT 1"));
    }
}
