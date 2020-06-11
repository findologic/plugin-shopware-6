<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter;

class RatingFilter extends Filter
{
    /** @var float */
    private $maxPoints = 0;

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
