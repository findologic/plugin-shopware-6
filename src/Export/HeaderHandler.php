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
    public const HEADER_SHOPWARE = 'x-findologic-platform';
    public const HEADER_PLUGIN = 'x-findologic-plugin';
    public const HEADER_EXTENSION = 'x-findologic-extension-plugin';
    public const HEADER_CONTENT_TYPE = 'content-type';

    public const CONTENT_TYPE_XML = 'text/xml';
    public const CONTENT_TYPE_JSON = 'application/json';

    private const SHOPWARE_VERSION = 'Shopware/%s';
    private const PLUGIN_VERSION = 'Plugin-Shopware-6/%s';
    private const EXTENSION_PLUGIN_VERSION = 'Plugin-Shopware-6-Extension/%s';

    private Context $context;

    private EntityRepository $repository;

    private string $shopwareVersion;

    private string $pluginVersion;

    private string $extensionPluginVersion;

    private string $contentType;

    public function __construct(
        EntityRepository $pluginRepository,
        string $shopwareVersion
    ) {
        $this->context = Context::createDefaultContext();
        $this->repository = $pluginRepository;

        $this->shopwareVersion = sprintf(self::SHOPWARE_VERSION, $shopwareVersion);
        $this->pluginVersion = $this->fetchPluginVersion();
        $this->extensionPluginVersion = $this->fetchExtensionPluginVersion();
        $this->contentType = self::CONTENT_TYPE_XML;
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public function getHeaders(array $overrides = []): array
    {
        $headers = [];
        $headers[self::HEADER_CONTENT_TYPE] = $this->contentType;
        $headers[self::HEADER_SHOPWARE] = $this->shopwareVersion;
        $headers[self::HEADER_PLUGIN] = $this->pluginVersion;
        $headers[self::HEADER_EXTENSION] = $this->extensionPluginVersion;

        return array_merge($headers, $overrides);
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
}
