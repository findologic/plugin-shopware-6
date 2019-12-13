<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Query;
use Shopware\Core\Framework\Struct\Struct;

class SmartDidYouMean extends Struct
{
    /** @var string */
    private $type;

    /** @var string */
    private $controllerPath;

    /** @var string|null */
    private $link;

    /** @var string */
    private $alternativeQuery;

    /** @var string */
    private $originalQuery;

    public function __construct(Query $query, string $controllerPath)
    {
        $this->type = $query->getDidYouMeanQuery() !== null ? 'did-you-mean' : $query->getQueryString()->getType();
        $this->alternativeQuery = $query->getAlternativeQuery();

        $originalQuery = $query->getOriginalQuery() !== null ? $query->getOriginalQuery()->getValue() : '';

        $this->originalQuery = $this->type === 'did-you-mean' ? '' : $originalQuery;
        $this->controllerPath = $controllerPath;
        $this->createLink();
    }

    private function createLink(): void
    {
        switch ($this->type) {
            case 'did-you-mean':
                $this->link = sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $this->controllerPath,
                    $this->alternativeQuery
                );

                return;
            case 'improved':
                $this->link = sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $this->controllerPath,
                    $this->originalQuery
                );

                return;
            default:
                $this->link = null;

                return;
        }
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
