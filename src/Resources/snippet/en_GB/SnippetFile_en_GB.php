<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Resources\snippet\en_GB;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_en_GB implements SnippetFileInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'finsearch.en_GB';
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return __DIR__ . '/finsearch.en_GB.json';
    }

    /**
     * @inheritDoc
     */
    public function getIso(): string
    {
        return 'en-GB';
    }

    /**
     * @inheritDoc
     */
    public function getAuthor(): string
    {
        return 'FINDOLOGIC GmbH';
    }

    /**
     * @inheritDoc
     */
    public function isBase(): bool
    {
        return false;
    }
}
