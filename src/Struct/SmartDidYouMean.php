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

    /**
     * @var string
     */
    private $alternativeQuery;

    /**
     * @var string
     */
    private $originalQuery;

    public function __construct(Query $query, string $controllerPath)
    {
        $this->type = $query->getDidYouMeanQuery() !== null ? 'did-you-mean' : $query->getQueryString()->getType();
        $this->alternativeQuery = $query->getAlternativeQuery();
        $this->originalQuery = $this->type === 'did-you-mean' ? '' : $query->getOriginalQuery()->getValue();

        $this->controllerPath = $controllerPath;

        $this->link = $this->createLink();
    }

    private function createLink()
    {
        switch ($this->type) {
            case 'did-you-mean':
                $this->link =
                    sprintf('%s?search=%s&forceOriginalQuery=1', $this->controllerPath, $this->alternativeQuery);

                return;
            case 'improved':
                $this->link = sprintf('%s?search=%s&forceOriginalQuery=1', $this->controllerPath, $this->originalQuery);

                return;
            default:
                $this->link = null;

                return;
        }
    }
}
