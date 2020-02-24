<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter;

use Shopware\Core\Framework\Struct\Struct;

class CustomFilters extends Struct
{
    /** @var Filter[] */
    private $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function addFilter(Filter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * @return Filter[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
