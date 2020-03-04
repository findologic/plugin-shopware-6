<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Subscriber;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Utils\Utils;
use GuzzleHttp\Client;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FrontendSubscriber implements EventSubscriberInterface
{
    /** @var Config */
    private $config;

    /** @var ServiceConfigResource */
    private $serviceConfigResource;

    /** @var ApiConfig */
    private $apiConfig;

    /** @var SearchRequestHandler */
    private $searchRequestHandler;

    /** @var NavigationRequestHandler */
    private $navigationRequestHandler;

    /** @var ContainerInterface */
    private $container;

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
