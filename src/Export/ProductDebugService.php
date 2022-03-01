<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Struct\Config;
use Psr\Container\ContainerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductDebugService extends ProductService
{
    public const CONTAINER_ID = 'fin_search.product_debug_service';

    public static function getInstance(
        ContainerInterface $container,
        ?SalesChannelContext $salesChannelContext,
        ?Config $config = null
    ): ProductService {
        if ($container->has(static::CONTAINER_ID)) {
            $productService = $container->get(static::CONTAINER_ID);
        } else {
            $productService = new ProductDebugService($container, $salesChannelContext, $config);
            $container->set(static::CONTAINER_ID, $productService);
        }

        if ($salesChannelContext && !$productService->getSalesChannelContext()) {
            $productService->setSalesChannelContext($salesChannelContext);
        }

        return $productService;
    }

    public function getDebugInformation(array $items): array
    {
        /** @var XMLItem $item */
        $item = $items[0];

        return [
            'export' => [
                'productId' => '',
                'exportedMainProductId' => '',
                'isExported' => true,
                'reasons' => []
            ],
            'debugLinks' => [
                'exportUrl' => '',
                'debugUrl' => ''
            ],
            'data' => [
                'isExportedMainVariant' => true,
                'product' => [],
                'siblings' => [],
                'associations' => [],
            ]
        ];
    }
}
