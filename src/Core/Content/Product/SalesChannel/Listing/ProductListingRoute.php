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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductListingRoute extends AbstractProductListingRoute
{
    use SearchResultHelper;

    public function __construct(
        private readonly AbstractProductListingRoute $decorated,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductDefinition $definition,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly ServiceConfigResource $serviceConfigResource,
        private readonly FindologicConfigService $findologicConfigService,
        private ?Config $config = null
    ) {
        $this->config = $config ?? new Config($findologicConfigService, $serviceConfigResource);
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $context,
        ?Criteria $criteria = null
    ): ProductListingRouteResponse {
        $criteria ??= $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $context->getContext()
        );

        $this->config->initializeBySalesChannel($context);
        $shouldHandleRequest = Utils::shouldHandleRequest(
            $request,
            $context->getContext(),
            $this->serviceConfigResource,
            $this->config,
            true
        );

        $isDefaultCategory = $categoryId === $context->getSalesChannel()->getNavigationCategoryId();
        if (!$shouldHandleRequest || $isDefaultCategory || !$this->isRouteSupported($request)) {
            Utils::disableFindologicWhenEnabled($context);

            return $this->decorated->load($categoryId, $request, $context, $criteria);
        }

        $criteria->addFilter(
            new ProductAvailableFilter(
                $context->getSalesChannel()->getId(),
                ProductVisibilityDefinition::VISIBILITY_ALL
            )
        );

        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(
            new Criteria([$categoryId]),
            $context->getContext()
        )->first();

        $streamId = $this->extendCriteria($context, $criteria, $category);

        $this->eventDispatcher->dispatch(
            new ProductListingCriteriaEvent($request, $criteria, $context)
        );

        $result = $this->doSearch($criteria, $context);

        $productListing = ProductListingResult::createFrom($result);
        $productListing->addCurrentFilter('navigationId', $categoryId);

        // Getter and setter for the stream id were only added in 6.4.0.0
        // This was added by SW to adapt the cache key
        if (method_exists($productListing, 'setStreamId')) {
            $productListing->setStreamId($streamId);
        }

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $productListing, $context)
        );

        return new ProductListingRouteResponse($productListing);
    }

    protected function doSearch(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        $this->assignPaginationToCriteria($criteria);
        $this->addOptionsGroupAssociation($criteria);

        if (empty($criteria->getIds())) {
            return $this->createEmptySearchResult($criteria, $salesChannelContext->getContext());
        }

        // Shopware can not handle _score sort for listing pages, but since our Findologic navigation always shows
        // top results, we reset it here shortly.
        $preservedSortings = $criteria->getSorting();
        $criteria->resetSorting();

        // Shopware sets the offset during their additional requests
        // Offset should not be set, when searching for explicit IDs
        $criteria->setOffset(null);

        $result = $this->fetchProducts($criteria, $salesChannelContext);
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

    private function extendCriteria(
        SalesChannelContext $salesChannelContext,
        Criteria $criteria,
        CategoryEntity $category
    ): ?string {
        $supportsProductStreams = defined(
            '\Shopware\Core\Content\Category\CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM'
        );
        $isProductStream = $supportsProductStreams &&
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
