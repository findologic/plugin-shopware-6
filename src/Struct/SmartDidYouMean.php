<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Query;
use Shopware\Core\Framework\Struct\Struct;

use SimpleXMLElement;
use function urlencode;

class SmartDidYouMean extends Struct
{
    /** @var null|string */
    private $type;

    /** @var string|null */
    private $link;

    /** @var string */
    private $alternativeQuery;

    /** @var string */
    private $originalQuery;

    public function __construct(Query $query, string $controllerPath)
    {
        $this->type = $query->getDidYouMeanQuery() !== null ? 'did-you-mean' : $query->getQueryString()->getType();
        $this->alternativeQuery = htmlentities($query->getAlternativeQuery());

        $originalQuery = $query->getOriginalQuery() !== null ? $query->getOriginalQuery()->getValue() : '';
        $this->originalQuery = $this->type === 'did-you-mean' ? '' : htmlentities($originalQuery);

        $this->link = $this->createLink($controllerPath);
    }

    private function createLink(string $controllerPath): ?string
    {
        switch ($this->type) {
            case 'did-you-mean':
                return sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $controllerPath,
                    $this->alternativeQuery
                );
            case 'improved':
                return sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $controllerPath,
                    $this->originalQuery
                );
            default:
                return null;
        }
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getAlternativeQuery(): string
    {
        return $this->alternativeQuery;
    }

    public function getOriginalQuery(): string
    {
        return $this->originalQuery;
    }
}
