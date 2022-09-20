<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Types;

use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use FINDOLOGIC\FinSearch\Export\Services\DynamicProductGroupService;
use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\Types\AbstractExport;
use FINDOLOGIC\Shopware6Common\Export\Adapters\ExportItemAdapter;
use FINDOLOGIC\Shopware6Common\Export\Types\ProductIdExport;
use FINDOLOGIC\Shopware6Common\Export\Types\XmlExport;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class Export
{
    public static function getInstance(
        int $type,
        DynamicProductGroupService $dynamicProductGroupService,
        ProductSearcher $productSearcher,
        PluginConfig $pluginConfig,
        ExportItemAdapter $exportItemAdapter,
        ?LoggerInterface $logger = null
    ): AbstractExport {
        switch ($type) {
            case AbstractExport::TYPE_XML:
                return new XmlExport(
                    $dynamicProductGroupService,
                    $productSearcher,
                    $pluginConfig,
                    $exportItemAdapter,
                    $logger
                );
            case AbstractExport::TYPE_PRODUCT_ID:
                return new ProductIdExport(
                    $dynamicProductGroupService,
                    $productSearcher,
                    $pluginConfig,
                    $exportItemAdapter,
                    $logger
                );
            default:
                throw new InvalidArgumentException(sprintf('Unknown export type %d.', $type));
        }
    }
}
