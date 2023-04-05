<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Handlers;

use FINDOLOGIC\Shopware6Common\Export\Handlers\AbstractHeaderHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;

class HeaderHandler extends AbstractHeaderHandler
{
    private Context $context;

    public function __construct(
        private readonly EntityRepository $pluginRepository,
        string $shopwareVersion
    ) {
        $this->context = Context::createDefaultContext();

        parent::__construct($shopwareVersion);
    }

    protected function fetchPluginVersion(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'FinSearch'));

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepository->search($criteria, $this->context)->getEntities()->first();
        if ($plugin !== null && $plugin->getActive()) {
            return sprintf(self::PLUGIN_VERSION, $plugin->getVersion());
        }

        return self::DEFAULT_VERSION_TEXT;
    }

    protected function fetchExtensionPluginVersion(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'ExtendFinSearch'));

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepository->search($criteria, $this->context)->getEntities()->first();
        if ($plugin !== null && $plugin->getActive()) {
            return sprintf(self::EXTENSION_PLUGIN_VERSION, $plugin->getVersion());
        }

        return self::DEFAULT_VERSION_TEXT;
    }
}
