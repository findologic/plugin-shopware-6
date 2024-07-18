<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\Api\Responses\Json10\Json10Response;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\FinSearch\Exceptions\Search\UnknownCategoryException;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\Parser\NavigationCategoryParser;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Utils\Utils as CommonUtils;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestHandler extends SearchNavigationRequestHandler
{
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        ServiceConfigResource $serviceConfigResource,
        FindologicRequestFactory $findologicRequestFactory,
        Config $config,
        ApiConfig $apiConfig,
        ApiClient $apiClient,
        SortingHandlerService $sortingHandlerService,
    ) {
        parent::__construct(
            $serviceConfigResource,
            $findologicRequestFactory,
            $config,
            $apiConfig,
            $apiClient,
            $sortingHandlerService
        );
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws CategoryNotFoundException
     */
    public function handleRequest(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $originalCriteria = clone $criteria;

        try {
            /** @var Json10Response $response */
            $response = $this->doRequest($request, $criteria, $context);

            $responseParser = ResponseParser::getInstance(
                $response,
                $this->serviceConfigResource,
                $this->config
            );
        } catch (ServiceNotAliveException | UnknownCategoryException $e) {
            // Set default pagination here, otherwise it will throw a division by zero exception as we have already
            // overwritten the limit before reaching this point
            $criteria->setLimit($originalCriteria->getLimit() ?: Pagination::DEFAULT_LIMIT);

            return;
        }

        $criteria->setIds($responseParser->getProductIds());

        // Test comment.
        $this->setPromotionExtension($context, $responseParser);

        $this->setPagination(
            $criteria,
            $responseParser
        );
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws ServiceNotAliveException
     * @throws UnknownCategoryException
     */
    public function doRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null
    ): Response {
        // Prevent exception if someone really tried to order by score on a category page.
        if ($request->query->get('sort') === 'score') {
            $criteria->resetSorting();
        }

        $categoryPath = $this->fetchCategoryPath($request, $context);

        // If we can't fetch the category path, we let Shopware handle the request.
        if (empty($categoryPath)) {
            throw new UnknownCategoryException();
        }

        /** @var NavigationRequest $navigationRequest */
        $navigationRequest = $this->findologicRequestFactory->getInstance($request);
        $navigationRequest->setSelected('cat', $categoryPath);
        $this->setUserGroup($context, $navigationRequest);
        $this->setPaginationParams($criteria, $navigationRequest, $limit);
        $this->sortingHandlerService->handle($navigationRequest, $criteria);

        if ($criteria->hasExtension('flFilters')) {
            $this->filterHandler->handleFilters($request, $criteria, $navigationRequest);
        }

        return $this->sendRequest($navigationRequest);
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function fetchCategoryPath(Request $request, SalesChannelContext $salesChannelContext): ?string
    {
        $navigationCategoryParser = new NavigationCategoryParser($this->categoryRepository);
        $category = $navigationCategoryParser->parse($request, $salesChannelContext);

        if (!$category) {
            return null;
        }

        if ($this->currentCategoryIsRootCategory($category, $salesChannelContext)) {
            return null;
        }

        $rootCategory = Utils::fetchNavigationCategoryFromSalesChannel(
            $this->categoryRepository,
            $salesChannelContext->getSalesChannel()
        );

        return CommonUtils::buildCategoryPath($category->getBreadcrumb(), $rootCategory->getBreadcrumb());
    }

    private function currentCategoryIsRootCategory(
        CategoryEntity $category,
        SalesChannelContext $salesChannelContext
    ): bool {
        return $category->getId() === $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
    }
}
