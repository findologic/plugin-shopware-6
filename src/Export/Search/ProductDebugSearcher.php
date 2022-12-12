<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Search\ProductDebugSearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Vin\ShopwareSdk\Data\Entity\EntityCollection;
use Vin\ShopwareSdk\Data\Entity\Product\ProductCollection;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity;

class ProductDebugSearcher extends ProductSearcher implements ProductDebugSearcherInterface
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

        return $product && $product->parentId
            ? $this->getProductById($product->parentId)
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

    /**
     * @param Criteria $criteria
     */
    public function searchProduct($criteria): ?ProductEntity
    {
        return $this->searchProducts($criteria)->first();
    }

    /**
     * @return ProductEntity[]
     */
    public function getSiblings(string $parentId, int $count): array
    {
        $parentProduct = $this->getProductById($parentId);
        $criteria = $this->productCriteriaBuilder
            ->withLimit($count)
            ->withParentIdFilterWithVisibility($parentProduct->id)
            ->withOutOfStockFilter()
            ->withPriceZeroFilter()
            ->withVariantAssociations(
                $parentProduct->categoryIds ?? $parentProduct->categoryTree,
                $parentProduct->propertyIds
            )
            ->build();

        return $this->searchProducts($criteria)->getElements();
    }

    /**
     * @param Criteria $criteria
     */
    public function searchProducts($criteria): EntityCollection
    {
        $productResult = $this->productRepository->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        /** @var ProductCollection $products */
        $products = Utils::createSdkCollection(
            ProductCollection::class,
            ProductEntity::class,
            $productResult->getEntities()
        );

        return $products;
    }
}
