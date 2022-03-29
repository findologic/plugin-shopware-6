<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Export\Errors\ExportErrors;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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

    public function getDebugInformation(string $productId, string $shopkey, ExportErrors $exportErrors): JsonResponse
    {
        $this->productId = $productId;
        $this->shopkey = $shopkey;
        $this->exportErrors = $exportErrors;

        /** @var ProductEntity $item */
        $this->product = $this->fetchProduct();

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
                'exportUrl' => $this->buildExportUrl($exportedMainProductId),
                'debugUrl' => $this->buildDebugUrl($exportedMainProductId)
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
        $result = $this->searchVisibleProducts(1, 0, $this->productId);

        if ($result->count() === 1) {
            if ($result->first()->getId() === $this->productId) {
                return true;
            } else {
                $this->reasons[] = 'Different variant is used in the export.';
            }
        } else {
            $this->reasons[] = 'Product could not be found or is not available for search.';
        }

        return false;
    }

    private function checkExportCriteria(): void
    {
        $criteriaFunctions = [
            'addGrouping' => 'No display group set',
            'handleAvailableStock' => 'Closeout product is out of stock',
            'addVisibilityFilter' => 'Product is not visible for search'
        ];

        foreach ($criteriaFunctions as $function => $errorMessage) {
            $criteria = $this->buildCriteria($this->productId, false);

            $this->$function($criteria);

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

    private function buildExportUrl(string $exportedMainProductId): string
    {
        return $this->buildUrlByPath('findologic', $exportedMainProductId);
    }

    private function buildDebugUrl(string $exportedMainProductId): string
    {
        return $this->buildUrlByPath('findologic/debug', $exportedMainProductId);
    }

    private function buildUrlByPath(string $path, string $exportedMainProductId): string
    {
        return sprintf(
            '%s/%s?shopkey=%s&productId=%s',
            $this->getShopDomain(),
            $path,
            $this->shopkey,
            $exportedMainProductId
        );
    }

    private function getShopDomain(): string
    {
        if ($domains = $this->getSalesChannelContext()->getSalesChannel()->getDomains()) {
            return $domains->first()->getUrl();
        }

        return '';
    }
}
