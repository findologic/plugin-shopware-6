<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

class TwigExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            new TwigTest('instanceof', null, ['node_class' => InstanceOfTest::class])
        ];
    }
}
