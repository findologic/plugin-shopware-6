<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Traits;

use FINDOLOGIC\FinSearch\Struct\Pagination;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait SearchResultHelper
{
    /**
     * When search results are fetched from the database, the ordering of the products is based on the
     * database structure, which is not what we want. We manually re-order them by the ID, so the
     * ordering matches the result that the FINDOLOGIC API returned.
     *
     * @param EntitySearchResult $result
     * @param Criteria $criteria
     *
     * @return EntitySearchResult
     */
    private function fixResultOrder(EntitySearchResult $result, Criteria $criteria): EntitySearchResult
    {
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

    public function createEmptySearchResult(Criteria $criteria, SalesChannelContext $context)
    {
        // Return an empty response, as Shopware would search for all products if no explicit
        // product ids are submitted.
        return new EntitySearchResult(
            0,
            new EntityCollection(),
            new AggregationResultCollection(),
            $criteria,
            $context->getContext()
        );
    }

    public function fetchProductsWithPagination(Criteria $criteria, SalesChannelContext $context)
    {
        /** @var Pagination $pagination */
        $pagination = $criteria->getExtension('flPagination');
        if ($pagination) {
            // Pagination is handled by FINDOLOGIC.
            $criteria->setLimit(24);
            $criteria->setOffset(0);
        }
        $result = $this->productRepository->search($criteria, $context);

        return $this->fixResultOrder($result, $criteria);
    }
}
