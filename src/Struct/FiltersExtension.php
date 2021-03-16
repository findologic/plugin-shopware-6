<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\FinSearch\Findologic\Response\Filter\BaseFilter;
use Shopware\Core\Framework\Struct\Struct;

class FiltersExtension extends Struct
{
    /** @var BaseFilter[] */
    private $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
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
