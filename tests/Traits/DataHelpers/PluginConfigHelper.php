<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait PluginConfigHelper
{
    protected static $PLUGIN_CONFIG_PREFIX = 'FinSearch.config.';

    public function enableFindologicPlugin(
        ContainerInterface $container,
        ?string $shopkey = null,
        ?SalesChannelContext $salesChannelContext = null
    ): void {
        /** @var FindologicConfigService $configService */
        $configService = $container->get(FindologicConfigService::class);

        $this->setConfig($configService, $salesChannelContext, 'active', true);
        $this->setConfig($configService, $salesChannelContext, 'shopkey', $shopkey);
    }

    public function setConfig(
        FindologicConfigService $configService,
        SalesChannelContext $salesChannelContext,
        string $key,
        $value
    ): void {
        $salesChannel = $salesChannelContext->getSalesChannel();
        $configService->set(
            self::$PLUGIN_CONFIG_PREFIX . $key,
            $value,
            $salesChannel->getId(),
            $salesChannel->getLanguageId()
        );
    }
}
