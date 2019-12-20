<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Query;
use Shopware\Core\Framework\Struct\Struct;

use function urlencode;

class SmartDidYouMean extends Struct
{
    /** @var string */
    protected $type;

    /** @var string|null */
    protected $link;

    /** @var string */
    protected $alternativeQuery;

    /** @var string */
    protected $originalQuery;

    public function __construct(Query $query, string $controllerPath)
    {
        $this->type = $query->getDidYouMeanQuery() !== null ? 'did-you-mean' : $query->getQueryString()->getType();
        $this->alternativeQuery = urlencode($query->getAlternativeQuery());

        $originalQuery = $query->getOriginalQuery() !== null ? $query->getOriginalQuery()->getValue() : '';
        $this->originalQuery = $this->type === 'did-you-mean' ? '' : urlencode($originalQuery);

        $this->link = $this->createLink($controllerPath);
    }

    private function createLink(string $controllerPath): ?string
    {
        switch ($this->type) {
            case 'did-you-mean':
                $link = sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $controllerPath,
                    $this->alternativeQuery
                );
                break;
            case 'improved':
                $link = sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $controllerPath,
                    $this->originalQuery
                );
                break;
            default:
                $link = null;
                break;
        }

        return $link;
    }
}
