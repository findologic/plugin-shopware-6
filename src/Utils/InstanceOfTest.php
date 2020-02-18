<?php

namespace FINDOLOGIC\FinSearch\Utils;

use Twig\Compiler;
use Twig\Node\Expression\TestExpression;

class InstanceOfTest extends TestExpression
{
    public function compile(Compiler $compiler)
    {
        $compiler
            ->raw('(')
            ->subcompile($this->getNode('node'))
            ->raw(' instanceof ')
            ->raw($this->getNode('arguments')->getNode(0)->getAttribute('value'))
            ->raw(')')
        ;
    }
}
