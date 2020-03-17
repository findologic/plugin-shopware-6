<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Exceptions\UnknownCategoryException;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\Parser\NavigationCategoryParser;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Config;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     *
     * @throws MissingRequestParameterException
     * @throws InconsistentCriteriaIdsException
     * @throws CategoryNotFoundException
     */
    public function handleRequest(ShopwareEvent $event): void
    {
        if (!$event->getContext()->getExtension('flEnabled')->getEnabled()) {
            return;
        }

        $originalCriteria = clone $event->getCriteria();

        try {
            /** @var Xml21Response $response */
            $response = $this->doRequest($event);
            $responseParser = ResponseParser::getInstance($response);
        } catch (ServiceNotAliveException | UnknownCategoryException $e) {
            $this->assignCriteriaToEvent($event, $originalCriteria);

            return;
        }

        /** @var Criteria $criteria */
        $criteria = $event->getCriteria();
        $this->setPagination(
            $criteria,
            $responseParser,
            $originalCriteria->getLimit(),
            $originalCriteria->getOffset()
        );

        $criteria->setIds($responseParser->getProductIds());
    }

    /**
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     * @param int|null $limit
     *
     * @return Response|null
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws ServiceNotAliveException
     * @throws UnknownCategoryException
     */
    public function doRequest(ShopwareEvent $event, ?int $limit = null): ?Response
    {
        if (!$event->getContext()->getExtension('flEnabled')->getEnabled()) {
            return null;
        }

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
            throw new UnknownCategoryException();
        }

        /** @var NavigationRequest $navigationRequest */
        $navigationRequest = $this->findologicRequestFactory->getInstance($request);
        $navigationRequest->setSelected('cat', $categoryPath);
        $this->setPaginationParams($event, $navigationRequest, $limit);
        $this->addSorting($navigationRequest, $event->getCriteria());

        if ($event->getCriteria()->hasExtension('flFilters')) {
            $this->filterHandler->handleFilters($event, $navigationRequest);
        }

        return $this->sendRequest($navigationRequest);
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

        if (!$category) {
            return null;
        }

        return $this->buildCategoryPath($category->getBreadcrumb());
    }

    private function buildCategoryPath(array $breadCrumbs): string
    {
        // Remove the first element as it is the main category.
        unset($breadCrumbs[0]);

        return implode('_', $breadCrumbs);
    }
}
