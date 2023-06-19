<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch;

use Composer\Autoload\ClassLoader;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Doctrine\DBAL\Connection;
use FINDOLOGIC\ExtendFinSearch\ExtendFinSearch;
use FINDOLOGIC\FinSearch\Exceptions\PluginNotCompatibleException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

use function file_get_contents;
use function json_decode;
use function method_exists;

class FinSearch extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        if (!$this->isCompatible($installContext)) {
            throw new PluginNotCompatibleException();
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($this->hasExtensionInstalled()) {
            $this->uninstallExtensionPlugin($uninstallContext);
        }

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->deleteFindologicConfig();
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        if ($this->hasExtensionInstalled()) {
            $plugin = $this->getExtensionPlugin($updateContext);

            if (Comparator::lessThan($plugin->getVersion(), '4.0')) {
                $this->uninstallExtensionPlugin($updateContext);
            }
        }
    }

    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function hasExtensionInstalled(): bool
    {
        $activePlugins = $this->container->getParameter('kernel.active_plugins');

        return isset($activePlugins[ExtendFinSearch::class]);
    }

    public function getExtensionPlugin(InstallContext $context): ?Plugin\PluginEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('plugin.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'ExtendFinSearch'));

        return $repository->search($criteria, $context->getContext())->getEntities()->first();
    }

    public function uninstallExtensionPlugin(InstallContext $context): void
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('plugin.repository');

        $plugin = $this->getExtensionPlugin($context);
        if ($plugin !== null && $plugin->getActive()) {
            $repository->delete([['id' => $plugin->getId()]], $context->getContext());
        }
    }

    /**
     * Pass `composerJsonPath` parameter specifically for unit-testing. In a real scenario, this will always be taken
     * from the plugin's actual `composer.json` file.
     */
    public static function isCompatible(InstallContext $installContext, string $composerJsonPath = null): bool
    {
        $currentVersion = $installContext->getCurrentShopwareVersion();
        if ($composerJsonPath === null) {
            $composerJsonPath = __DIR__ . '/../composer.json';
        }

        $composerJsonContents = file_get_contents($composerJsonPath);
        $parsed = json_decode($composerJsonContents, true);
        $requiredPackages = $parsed['require'];

        // If Shopware is not required in the json file, we probably are using the plugin's development version, so
        // the plugin will always be compatible in such a case.
        if (!isset($requiredPackages['shopware/core'])) {
            return true;
        }

        return Semver::satisfies($currentVersion, $requiredPackages['shopware/core']);
    }

    private function deleteFindologicConfig(): void
    {
        $connection = $this->container->get(Connection::class);
        if (method_exists($connection, 'executeStatement')) {
            $connection->executeStatement('DROP TABLE IF EXISTS `finsearch_config`');
        } else {
            $connection->executeUpdate('DROP TABLE IF EXISTS `finsearch_config`');
        }
    }
}
