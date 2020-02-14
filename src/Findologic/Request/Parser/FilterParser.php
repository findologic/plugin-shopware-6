<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Parser;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Filter;

class FilterParser
{
    /**
     * @var Filter[]
     */
    private $mainFilters;

    /**
     * @var Filter[]
     */
    private $otherFilters;

    public function __construct(array $mainFilters, array $otherFilters)
    {
        $this->mainFilters = $mainFilters;
        $this->otherFilters = $otherFilters;
    }

    public function transformFindologicFilters(): array
    {
        // TODO transform FINDOLOGIC filters into Shopware filters
        return [];
    }
}
