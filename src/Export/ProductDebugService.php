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

        return [
            'export' => [
                'productId' => $this->product->getId(),
                'exportedMainProductId' => $this->exportedMainVariantId(),
                'isExported' => true,
                'reasons' => $this->parseExportErrors()
            ],
            'debugLinks' => [
                'exportUrl' => $this->buildExportUrl(),
                'debugUrl' => $this->buildDebugUrl()
            ],
            'data' => [
                'isExportedMainVariant' => $this->exportedMainVariantId() === $this->product->getId(),
                'product' => $this->product,
                'siblings' => $this->product->getParentId() ? $this->getSiblings($this->product) : [],
                'associations' => $this->buildCriteria()->getAssociations(),
            ]
        ];
    }

    public function fetchProduct(?string $productId = null): ?ProductEntity
    {
        $criteria = $this->buildCriteria($productId);

        /** @var EntitySearchResult $entityResult */
        $entityResult = $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        return $this->buildProductsWithVariantInformation($entityResult)->first();
    }

    private function buildCriteria(?string $productId = null): Criteria
    {
        $criteria = new Criteria([$productId ?? $this->productId]);
        Utils::addProductAssociations($criteria);

        return $criteria;
    }

    private function fetchSiblings()
    {
        if (!$this->product->getParentId()) {
            return [];
        }

        $criteria = new Criteria();
        Utils::addProductAssociations($criteria);

        $criteria->addFilter(
            new EqualsFilter('parentId', $this->product->getParentId())
        );
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('product.id', $this->product->getId())]
            )
        );

        /** @var EntitySearchResult $entityResult */
        $entityResult = $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        return $entityResult->getElements();
    }

    private function exportedMainVariantId(): ?string
    {
        if (!$this->product->getParentId()) {
            return $this->product->getId();
        }

        if ($this->product->getMainVariantId() === $this->product->getId()) {
            return $this->product->getId();
        }

        return null;
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
