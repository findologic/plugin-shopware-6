<?php declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Subscriber;

use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FrontendSubscriber implements EventSubscriberInterface
{
    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var Config */
    private $config;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        $this->config = new Config($this->systemConfigService);
    }

    public static function getSubscribedEvents()
    {
        return [
            HeaderPageletLoadedEvent::class => 'onHeaderLoaded'
        ];
    }

    public function onHeaderLoaded(HeaderPageletLoadedEvent $event)
    {
        // This will store the plugin config for usage in our templates
        $event->getPagelet()->addExtension('flConfig', $this->config);
        if ($this->config->isActive()) {
            $shopkey = $this->config->getShopkey();
            $userGroupId = $event->getSalesChannelContext()->getCurrentCustomerGroup()->getId();
            $userGroupHash = Utils::calculateUserGroupHash($shopkey, $userGroupId);
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
