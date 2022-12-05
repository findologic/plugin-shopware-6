<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch;

use Composer\Autoload\ClassLoader;
use Composer\Semver\Comparator;
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

use function current;
use function end;
use function explode;
use function file_get_contents;
use function is_numeric;
use function json_decode;
use function ltrim;
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

            if (self::isVersionLower($plugin->getVersion(), ['4.0'])) {
                $this->uninstallExtensionPlugin($updateContext);
            }
        }
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

        $compatibleVersions = explode('||', $requiredPackages['shopware/core']);
        $isLower = self::isVersionLower($currentVersion, $compatibleVersions);
        $isHigher = self::isVersionHigher($currentVersion, $compatibleVersions);

        return !($isLower || $isHigher);
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

    protected static function isVersionLower(string $currentVersion, array $compatibleVersions): bool
    {
        $compatibleVersion = current($compatibleVersions);

        return Comparator::lessThan($currentVersion, $compatibleVersion);
    }

    protected static function isVersionHigher(string $currentVersion, array $compatibleVersions): bool
    {
        $compatibleVersion = end($compatibleVersions);
        $highestCompatible = ltrim($compatibleVersion, '^');
        if (is_numeric($highestCompatible)) {
            $highestCompatible += 0.1;
        }

        return Comparator::greaterThan($currentVersion, $highestCompatible);
    }
}

// phpcs:disable
/**
 * Shopware themselves use this method to autoload their libraries inside of plugins.
 *
 * @see https://github.com/shopware-blog/shopware-fastbill-connector/blob/development/src/FastBillConnector.php#L47
 */
$loader = require_once __DIR__ . '/../vendor/autoload.php';

// This is required, because FINDOLOGIC-API requires a later version of Guzzle than Shopware 6.
if ($loader instanceof ClassLoader) {
    $loader->unregister();
    $loader->register(false);
}
// phpcs:enable
