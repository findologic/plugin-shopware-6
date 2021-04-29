<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Traits;

use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

trait SearchResultHelper
{
    protected function createEmptySearchResult(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        // Return an empty response, as Shopware would search for all products if no explicit
        // product ids are submitted.
        return Utils::buildEntitySearchResult(
            ProductEntity::class,
            0,
            new EntityCollection(),
            new AggregationResultCollection(),
            $criteria,
            $context->getContext()
        );
    }

    protected function assignPaginationToCriteria(Criteria $criteria): void
    {
        /** @var Pagination $pagination */
        $pagination = $criteria->getExtension('flPagination');
        if ($pagination) {
            // Pagination is handled by FINDOLOGIC. If there is an existing limit set, we respect that,
            // otherwise use the default limit.
            $criteria->setLimit($criteria->getLimit() ?? Pagination::DEFAULT_LIMIT);
            $criteria->setOffset($criteria->getOffset() ?? 0);
        }
    }

    protected function fetchProducts(
        Criteria $criteria,
        SalesChannelContext $context,
        ?string $query = null
    ): EntitySearchResult {
        if ($query !== null && count($criteria->getIds()) === 1) {
            $this->modifyCriteriaFromQuery($query, $criteria, $context);
        }

        $result = $this->productRepository->search($criteria, $context);

        return $this->fixResultOrder($result, $criteria);
    }

    /**
     * When search results are fetched from the database, the ordering of the products is based on the
     * database structure, which is not what we want. We manually re-order them by the ID, so the
     * ordering matches the result that the FINDOLOGIC API returned.
     */
    private function fixResultOrder(EntitySearchResult $result, Criteria $criteria): EntitySearchResult
    {
        if (!$result->count()) {
            return $result;
        }

        $sortedElements = $this->sortElementsByIdArray($result->getElements(), $criteria->getIds());
        $result->clear();

        foreach ($sortedElements as $element) {
            $result->add($element);
        }

        return $result;
    }

    private function sortElementsByIdArray(array $elements, array $ids): array
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (is_array($id)) {
                $id = implode('-', $id);
            }

            if (array_key_exists($id, $elements)) {
                $sorted[$id] = $elements[$id];
            }
        }

        return $sorted;
    }

    private function getPage(Request $request): int
    {
        $page = $request->query->getInt('p', 1);

        if ($request->isMethod(Request::METHOD_POST)) {
            $page = $request->request->getInt('p', $page);
        }

        return $page <= 0 ? 1 : $page;
    }

    public function getOffset(Request $request, ?int $limit = null)
    {
        if (!$limit) {
            $limit = Pagination::DEFAULT_LIMIT;
        }

        $page = $this->getPage($request);

        return ($page - 1) * $limit;
    }

    /**
     * If a specific variant is searched by its product number, we want to modify the criteria
     * to show that variant instead of the main product.
     */
    private function modifyCriteriaFromQuery(
        string $query,
        Criteria $criteria,
        SalesChannelContext $context
    ): void {
        $productCriteria = new Criteria();
        $productCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('productNumber', $query),
            new EqualsFilter('ean', $query),
            new EqualsFilter('manufacturerNumber', $query),
        ]));
        $product = $this->productRepository->search($productCriteria, $context)->first();
        if ($product) {
            $criteria->setIds([$product->getId()]);
        }
    }
}
