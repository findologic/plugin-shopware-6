<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductDebugService extends ProductService
{
    public const CONTAINER_ID = 'fin_search.product_debug_service';

    public function getDebugInformation(string $shopkey, string $productId): array
    {
        $criteria = $this->buildCriteria($productId);
        /** @var ProductEntity $item */
        $item = $this->fetchProduct($criteria);

        if (!$item) {
            return $this->buildNotFoundResponse($productId);
        }

        return [
            'export' => [
                'productId' => $item->getId(),
                'exportedMainProductId' => $item->getMainVariantId(),
                'isExported' => true,
                'reasons' => []
            ],
            'debugLinks' => [
                'exportUrl' => sprintf(
                    'http://localhost:8000/findologic?shopkey=%s&productId=%s',
                    $shopkey,
                    $item->getMainVariantId() ?? $item->getId()
                ),
                'debugUrl' => sprintf(
                    'http://localhost:8000/findologic/debug?shopkey=%s&productId=%s',
                    $shopkey,
                    $item->getMainVariantId() ?? $item->getId()
                )
            ],
            'data' => [
                'isExportedMainVariant' => $item->getMainVariantId() ? $item->getMainVariantId() === $item->getId() : true,
                'product' => $item,
                'siblings' => $item->getChildren(),
                'associations' => $criteria->getAssociations(),
            ]
        ];
    }

    private function buildCriteria(string $productId)
    {
        $criteria = new Criteria([$productId]);
        Utils::addProductAssociations($criteria);

        return $criteria;
    }

    private function fetchProduct(Criteria $criteria): ?ProductEntity
    {
        /** @var EntitySearchResult $entityResult */
        $entityResult = $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );

        return $entityResult->first();
    }

    private function buildNotFoundResponse(string $productId): array
    {
        return [
            sprintf('Product or variant with ID %s does not exist.', $productId)
        ];
    }
}
