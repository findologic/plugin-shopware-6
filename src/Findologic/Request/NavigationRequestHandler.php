<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestHandler extends SearchNavigationRequestHandler
{
    /**
     * @var GenericPageLoader
     */
    private $genericPageLoader;

    public function __construct(
        ServiceConfigResource $serviceConfigResource,
        FindologicRequestFactory $findologicRequestFactory,
        Config $config,
        ApiConfig $apiConfig,
        ApiClient $apiClient,
        GenericPageLoader $genericPageLoader
    ) {
        parent::__construct($serviceConfigResource, $findologicRequestFactory, $config, $apiConfig, $apiClient);

        $this->genericPageLoader = $genericPageLoader;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws MissingRequestParameterException
     * @throws InconsistentCriteriaIdsException
     */
    public function handleRequest(ShopwareEvent $event)
    {
        /** @var Request $request */
        $request = $event->getRequest();

        $page = $this->genericPageLoader->load($request, $event->getSalesChannelContext());
        $page = NavigationPage::createFrom($page);

        /** @var CategoryEntity $category */
        $category = $page->getHeader()->getNavigation()->getActive();

        // Remove the first element as it is the main category
        $path = $category->getBreadcrumb();
        unset($path[0]);

        $categoryPath = implode('_', $path);

        // We simply return if the current page is not a category page
        if (empty($categoryPath)) {
            return;
        }

        /** @var NavigationRequest $navigationRequest */
        $navigationRequest = $this->findologicRequestFactory->getInstance($request);
        $navigationRequest->setSelected('cat', $categoryPath);

        $this->sendRequest($event, $navigationRequest);
    }
}
