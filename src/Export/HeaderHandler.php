<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HeaderHandler
{
    private const
        SHOPWARE_HEADER = 'x-findologic-platform',
        PLUGIN_HEADER = 'x-findologic-plugin',
        EXTENSION_HEADER = 'x-findologic-extension-plugin',
        CONTENT_TYPE_HEADER = 'content-type',
        SHOPWARE_VERSION = 'Shopware/%s',
        PLUGIN_VERSION = 'Plugin-Shopware-6/%s',
        EXTENSION_PLUGIN_VERSION = 'Plugin-Shopware-6-Extension/%s',
        CONTENT_TYPE = 'text/xml';

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepository
     */
    private $repository;

    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var string
     */
    private $pluginVersion;

    /**
     * @var string
     */
    private $extensionPluginVersion;

    /**
     * @var string
     */
    private $contentType;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->context = Context::createDefaultContext();
        $this->repository = $container->get('plugin.repository');

        $this->shopwareVersion = $this->fetchShopwareVersion();
        $this->pluginVersion = $this->fetchPluginVersion();
        $this->extensionPluginVersion = $this->fetchExtensionPluginVersion();
        $this->contentType = self::CONTENT_TYPE;
    }

    private function fetchShopwareVersion(): string
    {
        $shopwareVersion = $this->container->getParameter('kernel.shopware_version');

        return sprintf(self::SHOPWARE_VERSION, $shopwareVersion);
    }

    private function fetchPluginVersion(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'FinSearch'));

        /** @var PluginEntity $plugin */
        $plugin = $this->repository->search($criteria, $this->context)->getEntities()->first();
        if ($plugin !== null && $plugin->getActive()) {
            return sprintf(self::PLUGIN_VERSION, $plugin->getVersion());
        }

        return 'none';
    }

    private function fetchExtensionPluginVersion(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'ExtendFinSearch'));

        /** @var PluginEntity $plugin */
        $plugin = $this->repository->search($criteria, $this->context)->getEntities()->first();
        if ($plugin !== null && $plugin->getActive()) {
            return sprintf(self::EXTENSION_PLUGIN_VERSION, $plugin->getVersion());
        }

        return 'none';
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        $headers = [];
        $headers[self::CONTENT_TYPE_HEADER] = $this->contentType;
        $headers[self::SHOPWARE_HEADER] = $this->shopwareVersion;
        $headers[self::PLUGIN_HEADER] = $this->pluginVersion;
        $headers[self::EXTENSION_HEADER] = $this->extensionPluginVersion;

        return $headers;
    }

    public function getHeader(string $key): ?string
    {
        $headers = $this->getHeaders();
        if (array_key_exists($key, $headers)) {
            return $headers[$key];
        }

        return null;
    }
}
