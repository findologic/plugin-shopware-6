<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use FINDOLOGIC\FinSearch\Exceptions\PluginNotCompatibleException;
use FINDOLOGIC\FinSearch\FinSearch;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\Migration\MigrationTestBehaviour;
use Shopware\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FinSearchTest extends TestCase
{
    use PluginTestsHelper;
    use MigrationTestBehaviour;
    use KernelTestBehaviour;

    private const PLUGIN_NAME = 'FinSearchTestPlugin';

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var KernelPluginCollection
     */
    private $pluginCollection;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    protected function setUp(): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->beginTransaction();

        $this->container = $this->getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->getParameter('kernel.project_dir'),
            $this->container->get(PluginFinder::class)
        );
        $this->pluginCollection = $this->container->get(KernelPluginCollection::class);
        $this->connection = $this->container->get(Connection::class);
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService();

        $this->addTestPluginToKernel(self::PLUGIN_NAME);

        $this->context = Context::createDefaultContext();
    }

    private function addTestPluginToKernel($pluginName): void
    {
        $testPluginBaseDir = __DIR__ . '/_fixture/plugins/' . $pluginName;
        $class = '\\' . $pluginName . '\\' . $pluginName;

        require_once $testPluginBaseDir . '/src/' . $pluginName . '.php';

        $this->container->get(KernelPluginCollection::class)
            ->add(new $class(false, $testPluginBaseDir));
    }

    protected function tearDown(): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->rollBack();
    }

    private function createPluginLifecycleService(): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->pluginCollection,
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(AssetService::class),
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->systemConfigService
        );
    }

    public function testInstallPluginThrowsExceptionIfNotCompatibleWithShopwareVersion(): void
    {
        // $this->createPlugin($this->pluginRepo, $this->context);
        $plugin = $this->getPlugin($this->context);

        $this->expectException(PluginNotCompatibleException::class);
        $this->expectExceptionMessage('Dieses Plugin ist nicht kompatibel mit der verwendeten Shopware Version');
        $this->pluginLifecycleService->installPlugin($plugin, $this->context);
    }

    private function getPlugin(Context $context): PluginEntity
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        return $this->pluginService->getPluginByName(self::PLUGIN_NAME, $context);
    }
}
