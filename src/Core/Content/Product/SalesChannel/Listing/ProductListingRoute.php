<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Traits\SearchResultHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductListingRoute extends AbstractProductListingRoute
{
    use SearchResultHelper;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductDefinition
     */
    private $definition;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var AbstractProductListingRoute
     */
    private $decorated;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var ProductStreamBuilderInterface
     */
    private $productStreamBuilder;

    /**
     * @var ServiceConfigResource
     */
    private $serviceConfigResource;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        AbstractProductListingRoute $decorated,
        SalesChannelRepositoryInterface $productRepository,
        EntityRepositoryInterface $categoryRepository,
        ProductStreamBuilderInterface $productStreamBuilder,
        EventDispatcherInterface $eventDispatcher,
        ProductDefinition $definition,
        RequestCriteriaBuilder $criteriaBuilder,
        ServiceConfigResource $serviceConfigResource,
        FindologicConfigService $findologicConfigService,
        ?Config $config = null
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->definition = $definition;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->decorated = $decorated;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $salesChannelContext,
        ?Criteria $criteria = null
    ): ProductListingRouteResponse {
        $criteria = $criteria ?? $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $salesChannelContext->getContext()
        );

        $this->config->initializeBySalesChannel($salesChannelContext);
        $shouldHandleRequest = Utils::shouldHandleRequest(
            $request,
            $salesChannelContext->getContext(),
            $this->serviceConfigResource,
            $this->config,
            true
        );

        $isDefaultCategory = $categoryId === $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
        if (!$shouldHandleRequest || $isDefaultCategory || !$this->isRouteSupported($request)) {
            Utils::disableFindologicWhenEnabled($salesChannelContext);

            return $this->decorated->load($categoryId, $request, $salesChannelContext, $criteria);
        }

        $criteria->addFilter(
            new ProductAvailableFilter(
                $salesChannelContext->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_ALL
            )
        );

        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(
            new Criteria([$categoryId]),
            $salesChannelContext->getContext()
        )->first();

        $streamId = $this->addCategoryFilter($salesChannelContext, $criteria, $category);

        $this->eventDispatcher->dispatch(
            new ProductListingCriteriaEvent($request, $criteria, $salesChannelContext)
        );


        $result = $this->doSearch($criteria, $salesChannelContext);
        /** @var ProductListingResult $productListing */
        $productListing = ProductListingResult::createFrom($result);
        $productListing->addCurrentFilter('navigationId', $categoryId);
        $productListing->setStreamId($streamId);

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $productListing, $salesChannelContext)
        );

        return new ProductListingRouteResponse($productListing);
    }

    protected function doSearch(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        $this->assignPaginationToCriteria($criteria);

        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $context);
        }

        // Shopware can not handle _score sort for listing pages, but since our Findologic navigation always shows
        // top results, we reset it here shortly.
        $preservedSortings = $criteria->getSorting();
        $criteria->resetSorting();

        $result = $this->fetchProducts($criteria, $context);
        foreach ($preservedSortings as $sorting) {
            $criteria->addSorting($sorting);
        }

        return $result;
    }

    protected function isRouteSupported(Request $request): bool
    {
        // Findologic should never trigger on home page, even if there are categories that would allow it.
        if ($this->isHomePage($request)) {
            return false;
        }

        // In case request came from the home page, Findologic should not trigger on those.
        if ($this->isRequestFromHomePage($request)) {
            return false;
        }

        return true;
    }

    protected function isHomePage(Request $request): bool
    {
        return $request->getPathInfo() === '/';
    }

    protected function isRequestFromHomePage(Request $request): bool
    {
        if (!$request->isXmlHttpRequest()) {
            return false;
        }

        $referer = $request->headers->get('referer');
        if (!$referer || !is_string($referer)) {
            return false;
        }

        $refererPath = parse_url($request->headers->get('referer'), PHP_URL_PATH);
        $path = ltrim($refererPath, $request->getBasePath());

        return $path === '' || $path === '/';
    }

    private function addCategoryFilter(
        SalesChannelContext $salesChannelContext,
        Criteria $criteria,
        CategoryEntity $category
    ): ?string {
        $isProductStream =
            $category->getProductAssignmentType() === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM;
        if ($isProductStream && $category->getProductStreamId() !== null) {
            $filters = $this->productStreamBuilder->buildFilters(
                $category->getProductStreamId(),
                $salesChannelContext->getContext()
            );

            $criteria->addFilter(...$filters);

            return $category->getProductStreamId();
        }

        $criteria->addFilter(
            new EqualsFilter('product.categoriesRo.id', $category->getId())
        );

        return null;
    }
}
