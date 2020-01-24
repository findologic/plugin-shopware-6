<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\Parser\NavigationCategoryParser;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestHandler extends SearchNavigationRequestHandler
{
    /** @var GenericPageLoader */
    private $genericPageLoader;

    /** @var ContainerInterface */
    private $container;

    public function __construct(
        ServiceConfigResource $serviceConfigResource,
        FindologicRequestFactory $findologicRequestFactory,
        Config $config,
        ApiConfig $apiConfig,
        ApiClient $apiClient,
        GenericPageLoader $genericPageLoader,
        ContainerInterface $container
    ) {
        parent::__construct($serviceConfigResource, $findologicRequestFactory, $config, $apiConfig, $apiClient);

        $this->genericPageLoader = $genericPageLoader;
        $this->container = $container;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws MissingRequestParameterException
     * @throws InconsistentCriteriaIdsException
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     */
    public function handleRequest(ShopwareEvent $event): void
    {
        $originalCriteria = clone $event->getCriteria();

        // Prevent exception if someone really tried to order by score on a category page.
        if ($event->getRequest()->query->get('sort') === 'score') {
            $event->getCriteria()->resetSorting();
        }

        $request = $event->getRequest();

        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $event->getSalesChannelContext();

        $categoryPath = $this->fetchCategoryPath($request, $salesChannelContext);

        // If we can't fetch the category path, we let Shopware handle the request.
        if (empty($categoryPath)) {
            return;
        }

        /** @var NavigationRequest $navigationRequest */
        $navigationRequest = $this->findologicRequestFactory->getInstance($request);
        $navigationRequest->setSelected('cat', $categoryPath);
        $this->setPaginationParams($event, $navigationRequest);

        try {
            $response = $this->sendRequest($navigationRequest);

        } catch (ServiceNotAliveException $e) {
            $this->assignCriteriaToEvent($event, $originalCriteria);
            return;
        }

        /** @var Criteria $criteria */
        $criteria = $event->getCriteria();
        $criteria->setIds($this->parseProductIdsFromResponse($response));
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    private function fetchCategoryPath(Request $request, SalesChannelContext $salesChannelContext): ?string
    {
        $navigationCategoryParser = new NavigationCategoryParser($this->container, $this->genericPageLoader);
        $category = $navigationCategoryParser->parse($request, $salesChannelContext);

        return $this->buildCategoryPath($category->getBreadcrumb());
    }

    private function buildCategoryPath(array $breadCrumbs): string
    {
        // Remove the first element as it is the main category.
        unset($breadCrumbs[0]);

        return implode('_', $breadCrumbs);
    }
}
