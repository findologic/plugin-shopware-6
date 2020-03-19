<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter\FilterValues;

use FINDOLOGIC\FinSearch\Struct\Filter\Media;

abstract class ColorImageFilterValue extends FilterValue
{
    /** @var string */
    protected $displayType;

    /**
     * @var Media|null
     */
    protected $media;

    public function getMedia(): ?Media
    {
        return $this->media;
    }

    public function setMedia(?Media $media): void
    {
        $this->media = $media;
    }

    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    public function setDisplayType(string $displayType): void
    {
        $this->displayType = $displayType;
    }
}
