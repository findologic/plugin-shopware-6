<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Resources\app\storefront\src\snippets\en_GB;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_en_GB implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'finsearch.en_GB';
    }

    public function getPath(): string
    {
        return __DIR__ . '/finsearch.en_GB.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
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
