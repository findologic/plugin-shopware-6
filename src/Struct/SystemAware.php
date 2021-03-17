<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class SystemAware extends Struct
{
    public const IDENTIFIER = 'flSystemAware';

    /** @var RouterInterface */
    private $router;

    /** @var bool */
    private $supportsNewSearchWidget;

    /** @var bool */
    private $supportsFilterDisabling;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
        $this->supportsNewSearchWidget = $this->isNewSearchWidgetSupported();
        $this->supportsFilterDisabling = $this->isDynamicFilterDisablingSupported();
    }

    public function __sleep(): array
    {
        return [
            'supportsNewSearchWidget',
            'supportsFilterDisabling'
        ];
    }

    public function supportsNewSearchWidget(): bool
    {
        return $this->supportsNewSearchWidget;
    }

    public function supportsFilterDisabling(): bool
    {
        return $this->supportsFilterDisabling;
    }

    private function isNewSearchWidgetSupported(): bool
    {
        try {
            $this->router->generate('widgets.search.pagelet.v2', []);
        } catch (RouteNotFoundException $e) {
            return false;
        }

        return true;
    }

    private function isDynamicFilterDisablingSupported(): bool
    {
        try {
            $this->router->generate('widgets.search.filter', []);
        } catch (RouteNotFoundException $e) {
            return false;
        }

        return true;
    }
}
