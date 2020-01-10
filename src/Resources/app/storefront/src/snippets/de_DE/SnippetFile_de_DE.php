<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Resources\app\storefront\src\snippets\de_DE;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_de_DE implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'finsearch.de_DE';
    }

    public function getPath(): string
    {
        return __DIR__ . '/finsearch.de_DE.json';
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
