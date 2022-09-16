<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Services;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder;
use FINDOLOGIC\FinSearch\Export\Search\ProductDebugSearcher;
use FINDOLOGIC\Shopware6Common\Export\Errors\ExportErrors;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Services\AbstractProductDebugService;
use FINDOLOGIC\Shopware6Common\Export\Services\DebugUrlBuilderService;
use Shopware\Core\Content\Product\ProductEntity;

class ProductDebugService extends AbstractProductDebugService
{
    private ?ProductEntity $requestedProduct;

    private ?ProductEntity $exportedMainProduct;

    public function __construct(
        ExportContext $exportContext,
        ProductDebugSearcher $productDebugSearcher,
        ProductCriteriaBuilder $productCriteriaBuilder
    ) {
        parent::__construct($exportContext, $productDebugSearcher, $productCriteriaBuilder);
    }

    protected function initialize(
        string $productId,
        string $shopkey,
        ?XMLItem $xmlItem,
        $exportedMainProduct,
        ExportErrors $exportErrors
    ): void {
        parent::initialize($productId, $shopkey, $xmlItem, $exportedMainProduct, $exportErrors);

        $this->debugUrlBuilderService = new DebugUrlBuilderService($this->exportContext, $shopkey, 'findologic');
        $this->requestedProduct = $this->productDebugSearcher->getProductById($productId);
        $this->exportedMainProduct = $exportedMainProduct;
    }

    protected function getRequestedProduct(): ProductEntity
    {
        return $this->requestedProduct;
    }

    protected function getRequestedProductId(): string
    {
        return $this->requestedProduct->getId();
    }

    protected function getRequestedProductParentId(): ?string
    {
        return $this->requestedProduct->getParentId();
    }

    protected function isRequestedProductActive(): bool
    {
        return $this->requestedProduct->getActive();
    }

    protected function getExportedMainProductProduct(): ProductEntity
    {
        return $this->exportedMainProduct;
    }

    protected function getExportedMainProductProductId(): string
    {
        return $this->exportedMainProduct->getId();
    }
}
