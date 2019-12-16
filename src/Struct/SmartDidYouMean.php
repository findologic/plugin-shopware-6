<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Query;
use Shopware\Core\Framework\Struct\Struct;

class SmartDidYouMean extends Struct
{
    /** @var string */
    protected $type;

    /** @var string */
    protected $controllerPath;

    /** @var string|null */
    protected $link;

    /** @var string */
    protected $alternativeQuery;

    /** @var string */
    protected $originalQuery;

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
}
