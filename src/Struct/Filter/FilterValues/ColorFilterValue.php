<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter\FilterValues;

class ColorFilterValue extends ColorImageFilterValue
{
    /** @var string */
    protected $displayType = 'color';

    /**
     * @var string|null
     */
    private $colorHexCode;

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
}
