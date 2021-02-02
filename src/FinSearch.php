<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch;

use Composer\Autoload\ClassLoader;
use Composer\Semver\Comparator;
use Doctrine\DBAL\Connection;
use FINDOLOGIC\ExtendFinSearch\ExtendFinSearch;
use FINDOLOGIC\FinSearch\Exceptions\PluginNotCompatibleException;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use function current;
use function end;
use function explode;
use function file_get_contents;
use function json_decode;

class FinSearch extends Plugin
{
    public const COMPATIBILITY_PATH = '/Resources/config/compatibility';

    public function build(ContainerBuilder $container): void
    {
        // For maintaining compatibility with Shopware 6.1.x we load relevant services due to several
        // breaking changes introduced in Shopware 6.2
        // @link https://github.com/shopware/platform/blob/master/UPGRADE-6.2.md
        if ($path = $this->getCompatibilityLayerServicesFilePath()) {
            $this->loadServiceXml($container, $path);
        }

        parent::build($container);
    }

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

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->deleteFindologicConfig();
    }

    private function getCompatibilityLayerServicesFilePath(): ?string
    {
        if (Utils::versionLowerThan('6.2')) {
            return self::COMPATIBILITY_PATH . '/shopware61';
        }

        return null;
    }

    private function loadServiceXml($container, string $filePath): void
    {
        $loader = new XmlFileLoader(
            $container,
            new FileLocator($this->getPath() . $filePath)
        );

        $loader->load('services.xml');
    }

    private function isCompatible(InstallContext $installContext): bool
    {
        $currentShopwareVersion = $installContext->getCurrentShopwareVersion();
        $composerJsonContents = file_get_contents(__DIR__ . '/../composer.json');
        $parsed = json_decode($composerJsonContents, true);
        // Do nothing if there is no require property in the json as we probably are in development
        if (!$parsed) {
            return true;
        }

        $requiredPackages = $parsed['require'];
        if (isset($requiredPackages['shopware/core'])) {
            $compatibleVersions = explode('||', $requiredPackages['shopware/core']);
            $lowestSupported = current($compatibleVersions);
            $highestSupported = end($compatibleVersions);
            $isLower = Comparator::lessThan($currentShopwareVersion, $lowestSupported);
            $isHigher = Comparator::greaterThan($highestSupported, $currentShopwareVersion);

            if ($isLower || $isHigher) {
                return false;
            }
        }

        return true;
    }

    private function deleteFindologicConfig(): void
    {
        $connection = $this->container->get(Connection::class);
        $connection->executeUpdate('DROP TABLE IF EXISTS `finsearch_config`');
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
