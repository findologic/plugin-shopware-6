<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Exceptions\Export\Product;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Throwable;

class ProductHasCrossSellingCategoryException extends ProductInvalidException
{
    /** @var CategoryEntity */
    private $category;

    public function __construct(
        ProductEntity $failedProduct,
        CategoryEntity $category,
        string $message = '',
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($failedProduct, $message, $code, $previous);

        $this->category = $category;
    }

    public function getCategory(): CategoryEntity
    {
        return $this->category;
    }
}
