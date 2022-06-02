<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Image;
use FINDOLOGIC\FinSearch\Export\ProductImageService;
use Shopware\Core\Content\Product\ProductEntity;

class ImagesAdapter
{
    /** @var ProductImageService $productImageService */
    protected $productImageService;

    public function __construct(
        ProductImageService $productImageService
    ) {
        $this->productImageService = $productImageService;
    }

    /**
     * @return Image[]
     */
    public function adapt(ProductEntity $product): array
    {
        return $this->productImageService->getProductImages($product, false);
    }
}
