<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Subscriber;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Product;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Navigation\NavigationPage;
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

    /** @var SearchRequestFactory */
    private $searchRequestFactory;

    /** @var ApiConfig */
    private $apiConfig;

    /** @var ApiClient */
    private $apiClient;

    /** @var GenericPageLoader */
    private $genericPageLoader;

    /** @var NavigationRequestFactory */
    private $navigationRequestFactory;

    public function __construct(
        SystemConfigService $systemConfigService,
        ServiceConfigResource $serviceConfigResource,
        SearchRequestFactory $searchRequestFactory,
        NavigationRequestFactory $navigationRequestFactory,
        GenericPageLoader $genericPageLoader,
        ?Config $config = null,
        ?ApiConfig $apiConfig = null,
        ?ApiClient $apiClient = null
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->serviceConfigResource = $serviceConfigResource;
        $this->searchRequestFactory = $searchRequestFactory;
        $this->genericPageLoader = $genericPageLoader;
        $this->navigationRequestFactory = $navigationRequestFactory;

        $this->config = $config ?? new Config($this->systemConfigService, $this->serviceConfigResource);
        $this->apiConfig = $apiConfig ?? new ApiConfig();
        $this->apiClient = $apiClient ?? new ApiClient($this->apiConfig);
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            HeaderPageletLoadedEvent::class => 'onHeaderLoaded',
            ProductEvents::PRODUCT_SEARCH_CRITERIA => 'onSearch',
            ProductEvents::PRODUCT_LISTING_CRITERIA => 'onNavigation'
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

        if ($this->config->isActive()) {
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

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws CategoryNotFoundException
     * @throws MissingRequestParameterException
     * @throws InvalidArgumentException
     */
    public function onNavigation(ProductListingCriteriaEvent $event)
    {
        if (!$this->config->isActive()) {
            return;
        }

        $request = $event->getRequest();
        $context = $event->getSalesChannelContext();

        // Get the current active category path
        $page = $this->genericPageLoader->load($request, $context);
        $page = NavigationPage::createFrom($page);

        /** @var CategoryEntity $category */
        $category = $page->getHeader()->getNavigation()->getActive();

        // We simply return if the current page is not a category page
        if (!$category->getPath()) {
            return;
        }

        // If we have a category page, we parse the breadcrumbs to generate the category path
        $breadcrumbs = $category->getBreadcrumb();
        // Remove the first category as this is the main category in which all categories are
        unset($breadcrumbs[0]);
        $categoryPath = implode('_', $breadcrumbs);

        $navigationRequest = $this->navigationRequestFactory->getInstance($request, $categoryPath);

        try {
            $response = $this->apiClient->send($navigationRequest);
        } catch (ServiceNotAliveException $e) {
            return;
        }

        $productIds = array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $response->getProducts()
        );

        $cleanCriteria = new Criteria($productIds);
        $event->getCriteria()->assign($cleanCriteria->getVars());
    }

    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function onSearch(ProductSearchCriteriaEvent $event): void
    {
        if (!$this->config->isActive()) {
            return;
        }

        $originalCriteria = clone $event->getCriteria();

        $shopkey = $this->config->getShopkey();
        $isDirectIntegration = $this->serviceConfigResource->isDirectIntegration($shopkey);
        $isStagingShop = $this->serviceConfigResource->isStaging($shopkey);

        if ($isDirectIntegration || $isStagingShop) {
            return;
        }

        $this->apiConfig->setServiceId($shopkey);

        $request = $event->getRequest();

        $searchRequest = $this->searchRequestFactory->getInstance($request);
        $searchRequest->setQuery($request->query->get('search'));

        try {
            $response = $this->apiClient->send($searchRequest);
        } catch (ServiceNotAliveException $e) {
            $event->getCriteria()->assign($originalCriteria->getVars());

            return;
        }

        $productIds = array_map(
            static function (Product $product) {
                return $product->getId();
            },
            $response->getProducts()
        );

        $cleanCriteria = new Criteria($productIds);
        $event->getCriteria()->assign($cleanCriteria->getVars());
    }
}
