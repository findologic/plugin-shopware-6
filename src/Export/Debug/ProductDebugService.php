<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Debug;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Errors\ExportErrors;
use FINDOLOGIC\FinSearch\Export\ProductService;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
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
    private $product;

    /** @var ?XMLItem */
    private $xmlItem;

    /** @var DebugUrlBuilder */
    private $debugUrlBuilder;

    public function __construct(
        ContainerInterface $container,
        ?SalesChannelContext $salesChannelContext = null,
        ?Config $config = null
    ) {
        parent::__construct($container, $salesChannelContext, $config);
    }

    public function getDebugInformation(
        string $productId,
        string $shopkey,
        ?XMLItem $xmlItem,
        ExportErrors $exportErrors
    ): JsonResponse {
        $this->initialize($productId, $shopkey, $xmlItem, $exportErrors);

        if (!$this->product) {
            $this->exportErrors->addGeneralError(
                sprintf('Product or variant with ID %s does not exist.', $this->productId)
            );

            return new JsonResponse(
                $this->exportErrors->buildErrorResponse(),
                422
            );
        }

        $exportedMainProductId = isset($xmlItem) ? $xmlItem->getId() : null;
        $isExported = $this->isExported() && !$exportErrors->hasErrors();

        if (!$isExported) {
            $this->checkExportCriteria();
        }

        return new JsonResponse([
            'export' => [
                'productId' => $this->product->getId(),
                'exportedMainProductId' => $exportedMainProductId,
                'isExported' => $isExported,
                'reasons' => $this->parseExportErrors()
            ],
            'debugLinks' => [
                'exportUrl' => $this->debugUrlBuilder->buildExportUrl($exportedMainProductId),
                'debugUrl' => $this->debugUrlBuilder->buildDebugUrl($exportedMainProductId),
            ],
            'data' => [
                'isExportedMainVariant' => $exportedMainProductId === $this->product->getId(),
                'product' => $this->product,
                'siblings' => $this->product->getParentId() ? $this->getSiblings($this->product, false) : [],
                'associations' => $this->buildCriteria()->getAssociations(),
            ]
        ]);
    }

    private function initialize(
        string $productId,
        string $shopkey,
        ?XMLItem $xmlItem,
        ExportErrors $exportErrors
    ): void {
        $this->productId = $productId;
        $this->exportErrors = $exportErrors;
        $this->product = $this->fetchProduct();
        $this->xmlItem = $xmlItem;
        $this->debugUrlBuilder = new DebugUrlBuilder($this->getSalesChannelContext(), $shopkey);
    }

    public function fetchProduct(?string $productId = null, ?bool $withVariantInformation = false): ?ProductEntity
    {
        $criteria = $this->buildCriteria($productId, true, $withVariantInformation);

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

    private function buildCriteria(
        ?string $productId = null,
        ?bool $withAssociations = true,
        ?bool $withVariantInformation = false
    ): Criteria {
        $criteria = new Criteria();

        $multiFilter = new MultiFilter(MultiFilter::CONNECTION_OR);
        $multiFilter->addQuery(
            new EqualsFilter('id', $productId ?? $this->productId)
        );

        if ($withVariantInformation) {
            $multiFilter->addQuery(
                new EqualsFilter('parentId', $productId ?? $this->productId)
            );
        }

        $criteria->addFilter($multiFilter);

        if ($withAssociations) {
            Utils::addProductAssociations($criteria);
        }

        return $criteria;
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
        if (!$isExportedVariant = $this->xmlItem->getId() === $this->productId) {
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
            $criteria = $this->buildCriteria($this->productId, false);

            $this->$method($criteria);

            if (!$this->searchProduct($criteria)) {
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
