<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch;

use FINDOLOGIC\ExtendFinSearch\ExtendFinSearch;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FinSearch extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        require_once $this->getBasePath() . '/vendor/autoload.php';
        parent::build($container);
    }

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
