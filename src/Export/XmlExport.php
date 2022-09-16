<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Shopware6Common\Export\AbstractXmlExport;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;

class XmlExport extends AbstractXmlExport
{
    /**
     * @param ProductEntity $product
     */
    protected function getIdOfProductEntity($product): string
    {
        return $product->getId();
    }

    /**
     * @param ProductEntity $product
     */
    protected function getParentIdOfProductEntity($product): string
    {
        return $product->getParentId();
    }

    /**
     * @param ProductEntity $product
     */
    protected function getPropertyIdsOfProductEntity($product): ?array
    {
        return $product->getPropertyIds();
    }

    /**
     * @param ProductEntity $product
     */
    protected function getNameOfProductEntity($product): string
    {
        return $product->getTranslation('name');
    }

    /**
     * @param ProductEntity $product
     * @return CategoryEntity[]
     */
    protected function getCategoriesOfProductEntity($product): array
    {
        return $product->getCategories() ? $product->getCategories()->getElements() : [];
    }

    /**
     * @param CategoryEntity $category
     */
    protected function getIdOfCategoryEntity($category): string
    {
        return $category->getId();
    }

    /**
     * @param CategoryEntity $category
     */
    protected function getBreadcrumbsStringOfCategoryEntity($category): string
    {
        return implode(' < ', $category->getBreadcrumb());
    }
}
