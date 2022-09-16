<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use FINDOLOGIC\Shopware6Common\Export\AbstractExport;
use FINDOLOGIC\Shopware6Common\Export\Adapters\ExportItemAdapter;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\HttpFoundation\Response;

abstract class Export
{
    /**
     * Creates a Findologic-consumable XML file, containing all product data as XML representation.
     */
    public const TYPE_XML = 0;

    /**
     * May be used for debugging purposes. In case any of the products can not be exported due to any reasons,
     * the reason will be shown in JSON format. When all products are valid, the default XML export will be used
     * to generate a Findologic-consumable XML file.
     */
    public const TYPE_PRODUCT_ID = 1;

    public static function getInstance(
        int $type,
        DynamicProductGroupService $dynamicProductGroupService,
        ProductSearcher $productSearcher,
        ExportItemAdapter $exportItemAdapter,
        ContainerInterface $container,
        ?LoggerInterface $logger = null
    ): AbstractExport {
        switch ($type) {
            case AbstractExport::TYPE_XML:
                return new XmlExport(
                    $dynamicProductGroupService,
                    $productSearcher,
                    $exportItemAdapter,
                    $container,
                    $logger
                );
            case AbstractExport::TYPE_PRODUCT_ID:
                return new ProductIdExport(
                    $dynamicProductGroupService,
                    $productSearcher,
                    $exportItemAdapter,
                    $container,
                    $logger
                );
            default:
                throw new InvalidArgumentException(sprintf('Unknown export type %d.', $type));
        }
    }
}
