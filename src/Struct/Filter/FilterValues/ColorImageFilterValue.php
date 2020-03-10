<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter\FilterValues;

use FINDOLOGIC\FinSearch\Struct\Filter\Media;

class ColorImageFilterValue extends FilterValue
{
    /**
     * @var string|null
     */
    private $colorHexCode;

    /**
     * @var Media|null
     */
    private $media;

    /** @var string */
    private $displayType = 'color';
    /**
     * @return string|null
     */
    public function getColorHexCode(): ?string
    {
        return $this->colorHexCode;
    }

    /**
     * @param string|null $colorHexCode
     */
    public function setColorHexCode(?string $colorHexCode): void
    {
        $this->colorHexCode = $colorHexCode;
    }

    /**
     * @return Media|null
     */
    public function getMedia(): ?Media
    {
        return $this->media;
    }

    /**
     * @param Media|null $media
     */
    public function setMedia(?Media $media): void
    {
        $this->media = $media;
    }

    /**
     * @return string
     */
    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    /**
     * @param string $displayType
     */
    public function setDisplayType(string $displayType): void
    {
        $this->displayType = $displayType;
    }
}
