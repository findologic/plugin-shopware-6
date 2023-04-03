<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

class SmartDidYouMean extends Struct
{
    protected const DID_YOU_MEAN = 'did-you-mean';
    protected const IMPROVED = 'improved';
    protected const CORRECTED = 'corrected';

    private ?string $type;

    private ?string $link;

    private string $originalQuery;

    private string $alternativeQuery;

    private string $didYouMeanQuery;

    private string $improvedQuery;

    public function __construct(
        ?string $originalQuery,
        ?string $alternativeQuery,
        ?string $didYouMeanQuery,
        ?string $improvedQuery,
        ?string $controllerPath
    ) {
        $this->originalQuery = $didYouMeanQuery ?? htmlentities($originalQuery);
        $this->alternativeQuery = htmlentities($alternativeQuery ?? '');
        $this->didYouMeanQuery = $didYouMeanQuery ?? '';
        $this->improvedQuery = $improvedQuery ?? '';

        $this->type = $this->defineType();
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
            'improvedQuery' => $this->improvedQuery,
        ];
    }

    private function defineType(): string
    {
        if ($this->didYouMeanQuery) {
            return self::DID_YOU_MEAN;
        } elseif ($this->improvedQuery) {
            return self::IMPROVED;
        } elseif ($this->alternativeQuery) {
            return self::CORRECTED;
        }

        return '';
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
