<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class SmartDidYouMean extends Struct
{
    protected const DID_YOU_MEAN = 'did-you-mean';
    protected const IMPROVED = 'improved';

    private ?string $link;

    public function __construct(
        private ?string $originalQuery,
        private ?string $alternativeQuery,
        private ?string $type,
        ?string $didYouMeanQuery,
        ?string $controllerPath
    ) {
        $this->type = $didYouMeanQuery !== null ? self::DID_YOU_MEAN : $type;
        $this->alternativeQuery = htmlentities($alternativeQuery ?? '');
        $this->originalQuery = $this->type === self::DID_YOU_MEAN ? '' : htmlentities($originalQuery);

        $this->link = $this->createLink($controllerPath);
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

    public function getVars(): array
    {
        return [
            'type' => $this->type,
            'link' => $this->link,
            'alternativeQuery' => $this->alternativeQuery,
            'originalQuery' => $this->originalQuery,
        ];
    }

    private function createLink(?string $controllerPath): ?string
    {
        switch ($this->type) {
            case self::DID_YOU_MEAN:
                return sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $controllerPath,
                    $this->alternativeQuery
                );
            case self::IMPROVED:
                return sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $controllerPath,
                    $this->originalQuery
                );
            default:
                return null;
        }
    }
}
