<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Pagination extends Struct
{
    public const DEFAULT_LIMIT = 24;

    public function __construct(
        private ?int $limit,
        private ?int $offset,
        private readonly ?int $total
    ) {
        $this->limit = $limit ?? self::DEFAULT_LIMIT;
        $this->offset = $offset ?? 0;
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
