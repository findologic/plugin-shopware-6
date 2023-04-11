<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter;

class RatingFilter extends Filter
{
    private float $maxPoints = 0;

    public function setMaxPoints(float $maxPoints): RatingFilter
    {
        $this->maxPoints = $maxPoints;

        return $this;
    }

    public function getMaxPoints(): float
    {
        return $this->maxPoints;
    }
}
