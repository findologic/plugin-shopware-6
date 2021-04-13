<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

use Twig\Compiler;
use Twig\Node\Expression\TestExpression;

class InstanceOfTest extends TestExpression
{
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('(')
            ->subcompile($this->getNode('node'))
            ->raw(' instanceof ')
            ->raw($this->getNode('arguments')->getNode('0')->getAttribute('value'))
            ->raw(')')
        ;
    }
}
