<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FindologicHeaderHandler
{
    public const
        SHOPWARE_HEADER = 'x-findologic-platform',
        PLUGIN_HEADER = 'x-findologic-plugin',
        EXTENSION_HEADER = 'x-findologic-extension-plugin',
        CONTENT_TYPE = 'content-type';
    private const
        SHOPWARE_HEADER_VALUE = 'Shopware/%s',
        PLUGIN_HEADER_VALUE = 'Plugin-Shopware-6/%s',
        EXTENSION_HEADER_VALUE = 'Plugin-Shopware-6-Extension/%s',
        CONTENT_TYPE_HEADER_VALUE = 'text/xml';

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
    private $shopwareHeaderValue = 'none';

    /**
     * @var string
     */
    private $pluginHeaderValue = 'none';

    /**
     * @var string
     */
    private $extensionPluginHeaderValue = 'none';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->context = Context::createDefaultContext();
        $this->repository = $container->get('plugin.repository');

        $this->setShopwareVersionHeader();
        $this->setPluginVersionHeader();
        $this->setExtensionPluginHeader();
    }

    private function setShopwareVersionHeader(): void
    {
        $shopwareVersion = $this->container->getParameter('kernel.shopware_version');
        $this->shopwareHeaderValue = sprintf(self::SHOPWARE_HEADER_VALUE, $shopwareVersion);
    }

    private function setPluginVersionHeader(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'FinSearch'));

        /** @var PluginEntity $plugin */
        $plugin = $this->repository->search($criteria, $this->context)->getEntities()->first();
        if ($plugin !== null && $plugin->getActive()) {
            $this->pluginHeaderValue = sprintf(self::PLUGIN_HEADER_VALUE, $plugin->getVersion());
        }
    }

    private function setExtensionPluginHeader(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'ExtendFinSearch'));

        /** @var PluginEntity $plugin */
        $plugin = $this->repository->search($criteria, $this->context)->getEntities()->first();
        if ($plugin !== null && $plugin->getActive()) {
            $this->extensionPluginHeaderValue = sprintf(self::EXTENSION_HEADER_VALUE, $plugin->getVersion());
        }
    }

    public function getShopwareHeaderValue(): string
    {
        return $this->shopwareHeaderValue;
    }

    public function getPluginHeaderValue(): string
    {
        return $this->pluginHeaderValue;
    }

    public function getExtensionPluginHeaderValue(): string
    {
        return $this->extensionPluginHeaderValue;
    }

    public function getContentType(): string
    {
        return self::CONTENT_TYPE_HEADER_VALUE;
    }
}
