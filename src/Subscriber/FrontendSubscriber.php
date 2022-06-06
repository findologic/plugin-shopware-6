<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Subscriber;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Struct\PageInformation;
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

    /** @var FindologicConfigService */
    private $serviceConfigResource;

    public function __construct(
        FindologicConfigService $systemConfigService,
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
        $this->config->initializeBySalesChannel($event->getSalesChannelContext());

        // This will store the plugin config for usage in our templates
        $event->getPagelet()->addExtension('flConfig', $this->config);

        if (!$this->config->isActive()) {
            return;
        }

        $findologicService = new FindologicService();
        $event->getContext()->addExtension('findologicService', $findologicService);
        if ($this->config->isStaging() && !Utils::isStagingSession($event->getRequest())) {
            $findologicService->disable();
            $findologicService->disableSmartSuggest();
        } else {
            // Findologic won't be explicitly enabled here, as this is done at a later point.
            $findologicService->enableSmartSuggest();
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

        $request = $event->getRequest();
        $isSearchPage = $request->query->get('search') !== null && str_contains($request->getRequestUri(), '/search');
        $isNavigationPage = boolval($request->attributes->get('navigationId'));
        $pageInformation = new PageInformation($isSearchPage, $isNavigationPage);

        // Prepare pageInformation for usage in template
        $event->getPagelet()->addExtension('flPageInformation', $pageInformation);
    }
}
