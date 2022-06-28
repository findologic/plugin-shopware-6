<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

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
        RouterInterface $router,
        ContainerInterface $container,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        array $crossSellingCategories = []
    ): Export {
        switch ($type) {
            case self::TYPE_XML:
                return new XmlExport(
                    $router,
                    $container,
                    $logger,
                    $eventDispatcher,
                    $crossSellingCategories
                );
            case self::TYPE_PRODUCT_ID:
                return new ProductIdExport(
                    $router,
                    $container,
                    $logger,
                    $eventDispatcher,
                    $crossSellingCategories
                );
            default:
                throw new InvalidArgumentException(sprintf('Unknown export type %d.', $type));
        }
    }

    public function buildErrorResponse(ProductErrorHandler $errorHandler, array $headers): JsonResponse
    {
        $headers[HeaderHandler::HEADER_CONTENT_TYPE] = HeaderHandler::CONTENT_TYPE_JSON;

        return new JsonResponse(
            $errorHandler->getExportErrors()->buildErrorResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $headers
        );
    }

    /**
     * @param ProductEntity[] $productEntities
     */
    abstract public function buildItems(array $productEntities): array;

    /**
     * @param Item[] $items
     */
    abstract public function buildResponse(array $items, int $start, int $total, array $headers = []): Response;
}
