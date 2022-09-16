<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Debug;

use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class ProductDebugSearcher extends ProductSearcher
{
    public function buildCriteria(?int $limit = null, ?int $offset = null, ?string $productId = null): Criteria
    {
        $this->productCriteriaBuilder->withCreatedAtSorting()
            ->withIdSorting()
            ->withPagination($limit, $offset)
            ->withProductIdFilter($productId, true)
            ->withOutOfStockFilter()
            ->withProductAssociations();
        $this->adaptCriteriaBasedOnConfiguration();

        return $this->productCriteriaBuilder->build();
    }

    public function getMainProductById(string $productId): ?ProductEntity
    {
        $product = $this->getProductById($productId);

        return $product && $product->getParentId()
            ? $this->getProductById($product->getParentId())
            : $product;
    }

    public function getProductById(string $productId): ?ProductEntity
    {
        $criteria = $this->productCriteriaBuilder
            ->withIds([$productId])
            ->withProductAssociations()
            ->build();

        return $this->searchProduct($criteria);
    }

    public function searchProducts(Criteria $criteria): EntitySearchResult
    {
        return $this->productRepository->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );
    }

    public function searchProduct(Criteria $criteria): ?ProductEntity
    {
        return $this->searchProducts($criteria)->first();
    }

    public function getSiblings(string $parentId, int $count): array
    {
        $parentProduct = $this->getProductById($parentId);
        $criteria = $this->productCriteriaBuilder
            ->withLimit($count)
            ->withParentIdFilterWithVisibility($parentProduct->getId())
            ->withOutOfStockFilter()
            ->withPriceZeroFilter()
            ->withVariantAssociations()
            ->build();

        return $this->searchProducts($criteria)->getElements();
    }
}
