<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\QueryInfoMessage;

use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;

class CategoryInfoMessage extends QueryInfoMessage
{
    public function __construct(
        protected readonly string $filterName,
        protected string $filterValue
    ) {
        $this->filterValue = str_replace(
            FilterHandler::FILTER_DELIMITER_ENCODED,
            FilterHandler::FILTER_DELIMITER,
            $filterValue
        );
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
