<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait PluginConfigHelper
{
    protected static $PLUGIN_CONFIG_PREFIX = 'FinSearch.config.';

    public function enableFindologicPlugin(
        ContainerInterface $container,
        ?string $shopkey = null,
        ?SalesChannelContext $salesChannelContext = null
    ): void {
        /** @var SystemConfigService $configService */
        $configService = $container->get(SystemConfigService::class);

        $this->setConfig($configService, $salesChannelContext, 'active', true);
        $this->setConfig($configService, $salesChannelContext, 'shopkey', $shopkey);
    }

    public function setConfig(
        SystemConfigService $configService,
        ?SalesChannelContext $salesChannelContext,
        string $key,
        $value
    ): void {
        $configService->set(
            self::$PLUGIN_CONFIG_PREFIX . $key,
            $value,
            $salesChannelContext ? $salesChannelContext->getSalesChannel()->getId() : null
        );
    }
}
