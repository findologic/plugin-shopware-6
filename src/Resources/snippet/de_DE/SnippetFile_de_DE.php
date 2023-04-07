<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Resources\snippet\de_DE;

class SnippetFile_de_DE implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.de-DE';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.de-DE.json';
    }

    public function getIso(): string
    {
        return 'de-DE';
    }

    public function getAuthor(): string
    {
        return 'FINDOLOGIC GmbH';
    }

    public function isBase(): bool
    {
        return false;
    }
}
