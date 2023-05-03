<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\QueryInfoMessage;

class CategoryInfoMessage extends QueryInfoMessage
{
    public function __construct(
        protected readonly string $filterName,
        protected readonly string $filterValue
    ) {
    }

    public function getFilterName(): string
    {
        return $this->filterName;
    }

    public function getFilterValue(): string
    {
        return $this->filterValue;
    }
}
