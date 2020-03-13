<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Query;
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
     * @var ShopwareEvent
     */
    private $event;

    public function __construct(ShopwareEvent $event, Query $query)
    {
        $this->query = $query;
        $this->event = $event;

        $this->parse();
    }

    /**
     * @return string
     */
    public function getVendor(): string
    {
        return $this->vendor;
    }

    /**
     * @param string $vendor
     */
    public function setVendor(string $vendor): void
    {
        $this->vendor = $vendor;
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @param string $category
     */
    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    /**
     * @return string
     */
    public function getSmartQuery(): string
    {
        return $this->smartQuery;
    }

    /**
     * @param string $smartQuery
     */
    public function setSmartQuery(string $smartQuery): void
    {
        $this->smartQuery = $smartQuery;
    }

    /**
     * @return string
     */
    public function getSnippetType(): string
    {
        return $this->snippetType;
    }

    /**
     * @param string $snippetType
     */
    public function setSnippetType(string $snippetType): void
    {
        $this->snippetType = $snippetType;
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
            $categories = explode('_', $params['cat']);
            $this->setCategory(end($categories));
            $this->setSnippetType('cat');
        } elseif (isset($params['vendor']) && !empty($params['vendor'])) {
            $this->setVendor($params['vendor']);
            $this->setSnippetType('vendor');
        } else {
            $this->setSnippetType('default');
        }
    }
}
