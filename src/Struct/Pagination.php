<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Pagination extends Struct
{
    public const DEFAULT_LIMIT = 24;

    private ?int $offset;

    private ?int $limit;

    private ?int $total;

    public function __construct(?int $limit, ?int $offset, ?int $total)
    {
        $this->limit = $limit ?? self::DEFAULT_LIMIT;
        $this->offset = $offset ?? 0;
        $this->total = $total;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }
}
