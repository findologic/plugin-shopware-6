<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values;

use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Media;

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

    public function setMedia(?Media $media): self
    {
        $this->media = $media;

        return $this;
    }

    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    public function setDisplayType(string $displayType): self
    {
        $this->displayType = $displayType;

        return $this;
    }
}
