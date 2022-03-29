<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Errors\ExportErrors;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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

    public function getDebugInformation(string $productId, string $shopkey, ExportErrors $exportErrors): array
    {
        $this->productId = $productId;
        $this->shopkey = $shopkey;
        $this->exportErrors = $exportErrors;

        /** @var ProductEntity $item */
        $this->product = $this->fetchProduct();

        if (!$this->product) {
            return $this->buildNotFoundResponse();
        }

        $exportedMainProductId = $this->exportedMainVariantId();
        $isExported = $this->isExported();

        if (!$isExported) {
            $this->checkExportCriteria();
        }

        return [
            'export' => [
                'productId' => $this->product->getId(),
                'exportedMainProductId' => $exportedMainProductId,
                'isExported' => $isExported,
                'reasons' => array_merge($this->parseExportErrors(), $this->reasons)
            ],
            'debugLinks' => [
                'exportUrl' => $this->buildExportUrl(),
                'debugUrl' => $this->buildDebugUrl()
            ],
            'data' => [
                'isExportedMainVariant' => $exportedMainProductId === $this->product->getId(),
                'product' => $this->product,
                'siblings' => $this->product->getParentId() ? $this->getSiblings($this->product) : [],
                'associations' => $this->buildCriteria()->getAssociations(),
            ]
        ];
    }

    public function fetchProduct(?string $productId = null, ?bool $withVariantInformation = false): ?ProductEntity
    {
        $criteria = $this->buildCriteria($productId);

        /** @var EntitySearchResult $entityResult */
        $entityResult = $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        return $withVariantInformation
            ? $this->buildProductsWithVariantInformation($entityResult)->first()
            : $entityResult->first();
    }

    private function searchProduct(Criteria $criteria): ?ProductEntity
    {
        return $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
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

    private function buildExportUrl(): string
    {
        return $this->buildUrlByPath('findologic');
    }

    private function buildDebugUrl(): string
    {
        return $this->buildUrlByPath('findologic/debug');
    }

    private function buildUrlByPath(string $path): string
    {
        return sprintf(
            '%s/%s?shopkey=%s&productId=%s',
            'http://localhost:8000',
            $path,
            $this->shopkey,
            $this->exportedMainVariantId()
        );
    }

    /**
     * @return string[]
     */
    private function buildNotFoundResponse(): array
    {
        return [
            sprintf('Product or variant with ID %s does not exist.', $this->productId)
        ];
    }
}
