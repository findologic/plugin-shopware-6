<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values;

use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Media;

abstract class ColorImageFilterValue extends FilterValue
{
    protected string $displayType;

    protected ?Media $media;

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
