<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch;

use Composer\Autoload\ClassLoader;
use FINDOLOGIC\ExtendFinSearch\ExtendFinSearch;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class FinSearch extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        $activePlugins = $this->container->getParameter('kernel.active_plugins');

        // If the Extension plugin is installed we will uninstall it with the FinSearch base plugin
        if (isset($activePlugins[ExtendFinSearch::class])) {
            /** @var EntityRepository $repository */
            $repository = $this->container->get('plugin.repository');

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', 'ExtendFinSearch'));

            /** @var Plugin\PluginEntity $plugin */
            $plugin = $repository->search($criteria, $uninstallContext->getContext())->getEntities()->first();
            if ($plugin !== null && $plugin->getActive()) {
                $repository->delete([['id' => $plugin->getId()]], $uninstallContext->getContext());
            }
        }

        parent::uninstall($uninstallContext);
    }
}

/**
 * Shopware themselves use this method to autoload their libraries inside of plugins.
 * @see https://github.com/shopware-blog/shopware-fastbill-connector/blob/development/src/FastBillConnector.php#L47
 */
$loader = require_once __DIR__ . '/../vendor/autoload.php';

// This is required, because FINDOLOGIC-API requires a later version of Guzzle than Shopware 6.
if ($loader instanceof ClassLoader) {
    $loader->unregister();
    $loader->register(false);
}
