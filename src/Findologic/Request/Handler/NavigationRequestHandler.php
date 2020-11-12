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
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Utils\Utils;
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
        $originalCriteria = clone $event->getCriteria();

        try {
            /** @var Xml21Response $response */
            $response = $this->doRequest($event);

            $responseParser = ResponseParser::getInstance(
                $response,
                $this->serviceConfigResource,
                $this->config
            );
        } catch (ServiceNotAliveException | UnknownCategoryException $e) {
            // Set default pagination here, otherwise it will throw a division by zero exception as we have already
            // overwritten the limit before reaching this point
            $originalCriteria->setLimit($originalCriteria->getLimit() ?: Pagination::DEFAULT_LIMIT);
            $this->assignCriteriaToEvent($event, $originalCriteria);

            return;
        }

        $criteria = new Criteria($responseParser->getProductIds());
        $criteria->addExtensions($event->getCriteria()->getExtensions());

        $this->setPromotionExtension($event, $responseParser);

        $this->setPagination(
            $criteria,
            $responseParser,
            $originalCriteria->getLimit(),
            $originalCriteria->getOffset()
        );

        $this->assignCriteriaToEvent($event, $criteria);
    }

    /**
     * @param ShopwareEvent|ProductListingCriteriaEvent $event
     *
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws ServiceNotAliveException
     * @throws UnknownCategoryException
     */
    public function doRequest(ShopwareEvent $event, ?int $limit = null): Response
    {
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
        $this->setUserGroup($salesChannelContext, $navigationRequest);
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

        return Utils::buildCategoryPath($category->getBreadcrumb());
    }
}
