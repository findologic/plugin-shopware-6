<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Debug;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Errors\ExportErrors;
use FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProductDebugService
{
    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var ProductDebugSearcher */
    private $productDebugSearcher;

    /** @var ProductCriteriaBuilder */
    private $productCriteriaBuilder;

    /** @var string */
    private $productId;

    /** @var ExportErrors */
    private $exportErrors;

    /** @var ProductEntity */
    private $requestedProduct;

    /** @var ProductEntity */
    private $exportedMainProduct;

    /** @var ?XMLItem */
    private $xmlItem;

    /** @var DebugUrlBuilder */
    private $debugUrlBuilder;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        ProductDebugSearcher $productDebugSearcher,
        ProductCriteriaBuilder $productCriteriaBuilder
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->productDebugSearcher = $productDebugSearcher;
        $this->productCriteriaBuilder = $productCriteriaBuilder;
    }

    public function getDebugInformation(
        string $productId,
        string $shopkey,
        ?XMLItem $xmlItem,
        ?ProductEntity $exportedMainProduct,
        ExportErrors $exportErrors
    ): JsonResponse {
        $this->initialize($productId, $shopkey, $xmlItem, $exportedMainProduct, $exportErrors);

        if (!$this->requestedProduct) {
            $this->exportErrors->addGeneralError(
                sprintf('Product or variant with ID %s does not exist.', $this->productId)
            );

            return new JsonResponse(
                $this->exportErrors->buildErrorResponse(),
                422
            );
        }

        $isExported = $this->isExported() && !$exportErrors->hasErrors();

        if (!$isExported) {
            $this->checkExportCriteria();
        }

        return new JsonResponse([
            'export' => [
                'productId' => $this->requestedProduct->getId(),
                'exportedMainProductId' => $this->exportedMainProduct->getId(),
                'isExported' => $isExported,
                'reasons' => $this->parseExportErrors()
            ],
            'debugLinks' => [
                'exportUrl' => $this->debugUrlBuilder->buildExportUrl($this->exportedMainProduct->getId()),
                'debugUrl' => $this->debugUrlBuilder->buildDebugUrl($this->exportedMainProduct->getId()),
            ],
            'data' => [
                'isExportedMainVariant' => $this->exportedMainProduct->getId() === $this->requestedProduct->getId(),
                'product' => $this->requestedProduct,
                'siblings' => $this->requestedProduct->getParentId()
                    ? $this->productDebugSearcher->getSiblings($this->requestedProduct->getParentId(), 100)
                    : [],
                'associations' => $this->productDebugSearcher
                    ->buildCriteria()
                    ->getAssociations(),
            ]
        ]);
    }

    private function initialize(
        string $productId,
        string $shopkey,
        ?XMLItem $xmlItem,
        ?ProductEntity $exportedMainProduct,
        ExportErrors $exportErrors
    ): void {
        $this->debugUrlBuilder = new DebugUrlBuilder($this->salesChannelContext, $shopkey);

        $this->productId = $productId;
        $this->exportErrors = $exportErrors;
        $this->requestedProduct = $this->productDebugSearcher->getProductById($productId);
        $this->exportedMainProduct = $exportedMainProduct;
        $this->xmlItem = $xmlItem;
    }

    private function isExported(): bool
    {
        if ($isVisible = $this->isVisible()) {
            return $this->isExportedVariant();
        }

        return $isVisible;
    }

    private function isVisible(): bool
    {
        $isVisible = isset($this->xmlItem) && $this->requestedProduct->getActive();
        if (!$isVisible) {
            $this->exportErrors->addGeneralError('Product could not be found or is not available for search.');
        }

        return $isVisible;
    }

    private function isExportedVariant(): bool
    {
        if (!$isExportedVariant = $this->exportedMainProduct->getId() === $this->productId) {
            $this->exportErrors->addGeneralError('Product is not the exported variant.');
        }

        return $isExportedVariant;
    }

    private function checkExportCriteria(): void
    {
        $criteriaMethods = [
            'withDisplayGroupFilter' => 'No display group set',
            'withOutOfStockFilter' => 'Closeout product is out of stock',
            'withVisibilityFilter' => 'Product is not visible for search',
            'withPriceZeroFilter' => 'Product has a price of 0',
        ];

        foreach ($criteriaMethods as $method => $errorMessage) {
            $criteria = $this->productCriteriaBuilder
                ->withIds([$this->requestedProduct->getId()])
                ->$method()
                ->build();

            if (!$this->productDebugSearcher->searchProduct($criteria)) {
                $this->exportErrors->addGeneralError($errorMessage);
            }
        }
    }

    /**
     * @return string[]
     */
    private function parseExportErrors(): array
    {
        $errors = [];

        if ($this->exportErrors->hasErrors()) {
            $errors = array_merge($errors, $this->exportErrors->getGeneralErrors());

            if ($productErrors = $this->exportErrors->getProductError($this->productId)) {
                $errors = array_merge($errors, $productErrors->getErrors());
            }
        }

        return $errors;
    }
}
