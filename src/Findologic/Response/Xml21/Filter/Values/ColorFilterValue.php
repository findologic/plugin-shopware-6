<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values;

class ColorFilterValue extends ColorImageFilterValue
{
    /** @var string */
    protected $displayType = 'color';

    /**
     * @var string|null
     */
    private $colorHexCode;

    public function getColorHexCode(): ?string
    {
        return $this->colorHexCode;
    }

    public function setColorHexCode(?string $colorHexCode): self
    {
        $this->colorHexCode = $colorHexCode;

        return $this;
    }
}
