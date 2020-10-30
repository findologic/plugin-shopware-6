<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

abstract class Export
{
    public const TYPE_XML = 0;
    public const TYPE_PRODUCT_ID = 1;

    public static function getInstance(
        int $type,
        RouterInterface $router,
        ContainerInterface $container,
        LoggerInterface $logger,
        array $crossSellingCategories = []
    ): Export {
        switch ($type) {
            case self::TYPE_XML:
                return new XmlExport(
                    $router,
                    $container,
                    $logger,
                    $crossSellingCategories
                );
            case self::TYPE_PRODUCT_ID:
                return new ProductIdExport(
                    $router,
                    $container,
                    $logger,
                    $crossSellingCategories
                );
            default:
                throw new InvalidArgumentException(sprintf('Unknown Export type "%d"', $type));
        }
    }

    public function buildErrorResponseWithHeaders(ProductErrorHandler $errorHandler, array $headers): JsonResponse
    {
        $headers[HeaderHandler::CONTENT_TYPE_HEADER] = HeaderHandler::CONTENT_TYPE_JSON;

        return new JsonResponse(
            $errorHandler->getExportErrors()->buildErrorResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $headers
        );
    }

    /**
     * @param ProductEntity[] $productEntities
     * @param string $shopkey Required for generating the user group hash.
     * @param CustomerGroupEntity[] $customerGroups
     */
    abstract public function buildItems(
        array $productEntities,
        string $shopkey,
        array $customerGroups
    ): array;

    /**
     * @param Item[] $items
     */
    abstract public function buildResponse(array $items, int $start, int $total, array $headers = []): Response;
}
