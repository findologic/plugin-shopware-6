<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Query;
use FINDOLOGIC\FinSearch\Struct\Filter\Filter;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Struct\Struct;

class QueryInfoMessage extends Struct
{
    /**
     * @var string
     */
    private $vendor;

    /**
     * @var string
     */
    private $category;

    /**
     * @var string
     */
    private $smartQuery;

    /**
     * @var string
     */
    private $snippetType;

    /**
     * @var Query
     */
    private $query;

    /**
     * @var string
     */
    private $filterName;

    /**
     * @var ShopwareEvent
     */
    private $event;

    /**
     * @var Filter[]
     */
    private $filters;

    public function __construct(ShopwareEvent $event, Response $response)
    {
        $this->query = $response->getQuery();
        $this->event = $event;
        $this->filters = array_merge($response->getMainFilters(), $response->getOtherFilters());

        $this->parse();
    }

    public function getVendor(): string
    {
        return $this->vendor;
    }

    public function setVendor(string $vendor): void
    {
        $this->vendor = $vendor;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function getSmartQuery(): string
    {
        return $this->smartQuery;
    }

    public function setSmartQuery(string $smartQuery): void
    {
        $this->smartQuery = $smartQuery;
    }

    public function getSnippetType(): string
    {
        return $this->snippetType;
    }

    public function setSnippetType(string $snippetType): void
    {
        $this->snippetType = $snippetType;
    }

    public function getFilterName(): string
    {
        return $this->filterName;
    }

    public function setFilterName(string $filterName): void
    {
        $this->filterName = $filterName;
    }

    private function parse(): void
    {
        $queryStringType = $this->query->getQueryString()->getType();
        $queryString = $this->query->getQueryString()->getValue();

        $params = $this->event->getRequest()->query->all();

        /** @var SmartDidYouMean $flSmartDidYouMean */
        $flSmartDidYouMean = $this->event->getContext()->getExtension('flSmartDidYouMean');

        if (!empty($queryString) && (($queryStringType === 'corrected') || ($queryStringType === 'improved'))) {
            $this->setSnippetType('query');
            $this->setSmartQuery($flSmartDidYouMean->getAlternativeQuery());
        } elseif (!empty($queryString)) {
            $this->setSnippetType('query');
            $this->setSmartQuery($queryString);
        } elseif (isset($params['cat']) && !empty($params['cat'])) {
            $this->setFilterName($this->filters['cat']->getDisplay());
            $categories = explode('_', $params['cat']);
            $this->setCategory(end($categories));
            $this->setSnippetType('cat');
        } elseif (isset($params['vendor']) && !empty($params['vendor'])) {
            $this->setFilterName($this->filters['vendor']->getDisplay());
            $this->setVendor($params['vendor']);
            $this->setSnippetType('vendor');
        } else {
            $this->setSnippetType('default');
        }
    }
}
