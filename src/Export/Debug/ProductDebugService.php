<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Debug;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Errors\ExportErrors;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Struct\Config;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProductDebugService extends ProductService
{
    public const CONTAINER_ID = 'fin_search.product_debug_service';

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

    /** @var DebugProductSearch */
    private $debugProductSearch;

    public function __construct(
        ContainerInterface $container,
        ?SalesChannelContext $salesChannelContext = null,
        ?Config $config = null
    ) {
        parent::__construct($container, $salesChannelContext, $config);

        $this->debugProductSearch = new DebugProductSearch($this->getContainer(), $this->getSalesChannelContext());
    }

    public function getDebugInformation(
        string $productId,
        string $shopkey,
        ?XMLItem $xmlItem,
        ExportErrors $exportErrors
    ): JsonResponse {
        $this->initialize($productId, $shopkey, $xmlItem, $exportErrors);

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
                'siblings' => $this->requestedProduct->getParentId() ? $this->getSiblings($this->requestedProduct, false) : [],
                'associations' => $this->debugProductSearch->buildCriteria($this->requestedProduct->getId())->getAssociations(),
            ]
        ]);
    }

    private function initialize(
        string $productId,
        string $shopkey,
        ?XMLItem $xmlItem,
        ExportErrors $exportErrors
    ): void {
        $this->debugUrlBuilder = new DebugUrlBuilder($this->getSalesChannelContext(), $shopkey);

        $this->productId = $productId;
        $this->exportErrors = $exportErrors;
        $this->requestedProduct = $this->debugProductSearch->fetchProductResult($productId)->first();
        $this->exportedMainProduct = $this->fetchProductWithVariantInformation();
        $this->xmlItem = $xmlItem;
    }

    public function fetchProductWithVariantInformation(?string $productId = null): ?ProductEntity
    {
        $entityResult = $this->debugProductSearch->fetchProductResult($productId ?? $this->productId, true);

        return $this->buildProductsWithVariantInformation($entityResult)->first();
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
        if (!$isVisible = isset($this->xmlItem)) {
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
            'addGrouping' => 'No display group set',
            'handleAvailableStock' => 'Closeout product is out of stock',
            'addVisibilityFilter' => 'Product is not visible for search',
            'addPriceZeroFilter' => 'Product has a price of 0',
        ];

        foreach ($criteriaMethods as $method => $errorMessage) {
            $criteria = $this->debugProductSearch->buildCriteria($this->productId, false);

            $this->$method($criteria);

            if (!$this->debugProductSearch->searchProduct($criteria)) {
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
