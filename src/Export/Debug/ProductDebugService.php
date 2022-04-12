<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Debug;

use FINDOLOGIC\FinSearch\Export\Errors\ExportErrors;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProductDebugService extends ProductService
{
    public const CONTAINER_ID = 'fin_search.product_debug_service';

    /** @var string */
    private $productId;

    /** @var $shopkey */
    private $shopkey;

    /** @var ExportErrors */
    private $exportErrors;

    /** @var ProductEntity */
    private $product;

    /** @var string[] */
    private $reasons = [];

    /** @var DebugUrlBuilder */
    private $debugUrlBuilder;

    public function __construct(
        ContainerInterface $container,
        ?SalesChannelContext $salesChannelContext = null,
        ?Config $config = null
    ) {
        parent::__construct($container, $salesChannelContext, $config);
    }

    public function initialize(string $productId, string $shopkey, ExportErrors $exportErrors): void
    {
        $this->productId = $productId;
        $this->shopkey = $shopkey;
        $this->exportErrors = $exportErrors;
        $this->product = $this->fetchProduct();
        $this->debugUrlBuilder = new DebugUrlBuilder($this->getSalesChannelContext(), $shopkey);
    }

    public function getDebugInformation(string $productId, string $shopkey, ExportErrors $exportErrors): JsonResponse
    {
        $this->initialize($productId, $shopkey, $exportErrors);

        if (!$this->product) {
            $this->exportErrors->addGeneralError(
                sprintf('Product or variant with ID %s does not exist.', $this->productId)
            );

            return new JsonResponse(
                $this->exportErrors->buildErrorResponse(),
                422
            );
        }

        $exportedMainProductId = $this->exportedMainVariantId();
        $isExported = $this->isExported() && !$exportErrors->hasErrors();

        if (!$isExported) {
            $this->checkExportCriteria();
        }

        return new JsonResponse([
            'export' => [
                'productId' => $this->product->getId(),
                'exportedMainProductId' => $exportedMainProductId,
                'isExported' => $isExported,
                'reasons' => array_merge($this->parseExportErrors(), $this->reasons)
            ],
            'debugLinks' => [
                'exportUrl' => $this->debugUrlBuilder->buildExportUrl($exportedMainProductId),
                'debugUrl' => $this->debugUrlBuilder->buildDebugUrl($exportedMainProductId),
            ],
            'data' => [
                'isExportedMainVariant' => $exportedMainProductId === $this->product->getId(),
                'product' => $this->product,
                'siblings' => $this->product->getParentId() ? $this->getSiblings($this->product) : [],
                'associations' => $this->buildCriteria()->getAssociations(),
            ]
        ]);
    }

    public function fetchProduct(?string $productId = null, ?bool $withVariantInformation = false): ?ProductEntity
    {
        $criteria = $this->buildCriteria($productId);

        /** @var EntitySearchResult $entityResult */
        $entityResult = $this->getContainer()->get('product.repository')->search(
            $criteria,
            $this->getSalesChannelContext()->getContext()
        );

        return $withVariantInformation
            ? $this->buildProductsWithVariantInformation($entityResult)->first()
            : $entityResult->first();
    }

    private function searchProduct(Criteria $criteria): ?ProductEntity
    {
        return $this->getContainer()->get('product.repository')->search(
            $criteria,
            $this->getSalesChannelContext()->getContext()
        )->first();
    }

    private function buildCriteria(?string $productId = null, ?bool $withAssociations = true): Criteria
    {
        $criteria = new Criteria([$productId ?? $this->productId]);

        if ($withAssociations) {
            Utils::addProductAssociations($criteria);
        }

        return $criteria;
    }

    private function exportedMainVariantId(): string
    {
        $product = $this->fetchProduct($this->productId, true);

        return $product->getId();
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
        $result = $this->searchVisibleProducts(1, 0, $this->productId);

        if (!$isVisible = $result->count() === 1) {
            $this->reasons[] = 'Product could not be found or is not available for search.';
        }

        return $isVisible;
    }

    private function isExportedVariant(): bool
    {
        $result = $this->searchVisibleProducts(1, 0, $this->productId);

        if (!$isExportedVariant = $result->first()->getId() === $this->productId) {
            $this->reasons[] = 'Product could not be found or is not available for search.';
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
            $criteria = $this->buildCriteria($this->productId, false);

            $this->$method($criteria);

            if (!$this->searchProduct($criteria)) {
                $this->reasons[] = $errorMessage;
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
