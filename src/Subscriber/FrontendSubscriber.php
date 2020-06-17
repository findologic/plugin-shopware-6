<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Subscriber;

use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FrontendSubscriber implements EventSubscriberInterface
{
    /** @var Config */
    private $config;

    /** @var ServiceConfigResource */
    private $serviceConfigResource;

    public function __construct(
        SystemConfigService $systemConfigService,
        ServiceConfigResource $serviceConfigResource,
        ?Config $config = null
    ) {
        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config ?? new Config($systemConfigService, $serviceConfigResource);
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
        $this->config->initializeBySalesChannel($event->getSalesChannelContext()->getSalesChannel()->getId());

        // This will store the plugin config for usage in our templates
        $event->getPagelet()->addExtension('flConfig', $this->config);

        if (!$this->config->isActive()) {
            return;
        }

        $flEnabled = new FindologicEnabled();
        $event->getContext()->addExtension('flEnabled', $flEnabled);
        if ($this->config->isStaging() && !Utils::isStagingSession($event->getRequest())) {
            $flEnabled->setDisabled();
        }

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
