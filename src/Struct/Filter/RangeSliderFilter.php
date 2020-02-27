<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter;

class RangeSliderFilter extends Filter
{
    /**
     * @var string
     */
    private $minKey;

    /**
     * @var string
     */
    private $maxKey;

    public function __construct(string $id, string $name, array $values = [])
    {
        parent::__construct($id, $name, $values);
        $this->minKey = 'min-' . $id;
        $this->maxKey = 'max-' . $id;
    }

    /**
     * @return string
     */
    public function getMinKey(): string
    {
        return $this->minKey;
    }

    /**
     * @return string
     */
    public function getMaxKey(): string
    {
        return $this->maxKey;
    }
}
