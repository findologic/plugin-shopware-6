<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use Shopware\Core\Framework\Struct\Struct;

class FiltersExtension extends Struct
{
    /**
     * @param BaseFilter[] $filters
     */
    public function __construct(
        private array $filters = []
    ) {
    }

    public function addFilter(BaseFilter $filter): self
    {
        $this->filters[$filter->getId()] = $filter;

        return $this;
    }

    /**
     * @return BaseFilter[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFilter(string $id): ?BaseFilter
    {
        if (!isset($this->filters[$id])) {
            return null;
        }

        return $this->filters[$id];
    }
}
