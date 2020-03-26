<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\QueryInfoMessage;

class SearchTermQueryInfoMessage extends QueryInfoMessage
{
    /** @var string */
    protected $query;

    public function __construct(string $query)
    {
        $this->query = $query;
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}
