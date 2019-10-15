<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FinSearch extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        require_once $this->getBasePath() . '/vendor/autoload.php';
        parent::build($container);
    }
}
