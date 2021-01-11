<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Exceptions\Export\Product;

use FINDOLOGIC\FinSearch\Exceptions\Export\ExportException;
use Shopware\Core\Content\Product\ProductEntity;
use Throwable;

class ProductInvalidException extends ExportException
{
    /** @var ProductEntity */
    private $failedProduct;

    public function __construct(ProductEntity $failedProduct, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->failedProduct = $failedProduct;

        parent::__construct($message, $code, $previous);
    }

    public function getProduct(): ProductEntity
    {
        return $this->failedProduct;
    }
}
