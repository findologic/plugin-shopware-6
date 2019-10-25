<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Subscriber;

use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FrontendSubscriber implements EventSubscriberInterface
{
    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var Config */
    private $config;

    /** @var ServiceConfigResource */
    private $serviceConfigResource;

    public function __construct(SystemConfigService $systemConfigService, ServiceConfigResource $serviceConfigResource)
    {
        $this->systemConfigService = $systemConfigService;
        $this->config = new Config($this->systemConfigService, $serviceConfigResource);
        $this->serviceConfigResource = $serviceConfigResource;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            HeaderPageletLoadedEvent::class => 'onHeaderLoaded'
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function onHeaderLoaded(HeaderPageletLoadedEvent $event): void
    {
        // This will store the plugin config for usage in our templates
        $event->getPagelet()->addExtension('flConfig', $this->config);
        if ($this->config->isActive()) {
            $this->config->initializeBySalesChannel(
                $event->getSalesChannelContext()->getSalesChannel()->getId(),
                $this->serviceConfigResource
            );
            $shopkey = $this->config->getShopkey();
            $customerGroupId = $event->getSalesChannelContext()->getCurrentCustomerGroup()->getId();
            $userGroupHash = Utils::calculateUserGroupHash($shopkey, $customerGroupId);
            $snippet = new Snippet(
                $shopkey,
                $this->config->getSearchResultContainer(),
                $this->config->getNavigationResultContainer(),
                $userGroupHash
            );

            // Save the snippet for usage in template
            $event->getPagelet()->addExtension('flSnippet', $snippet);
        }
    }
}
