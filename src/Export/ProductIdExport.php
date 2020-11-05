<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\XML\XMLExporter;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class ProductIdExport extends XmlExport
{
    /** @var ProductErrorHandler */
    private $errorHandler;

    public function __construct(
        RouterInterface $router,
        ContainerInterface $container,
        LoggerInterface $logger,
        array $crossSellingCategories = [],
        ?XMLExporter $exporter = null
    ) {
        parent::__construct($router, $container, $logger, $crossSellingCategories, $exporter);

        $this->errorHandler = $this->pushErrorHandler();
    }

    public function getErrorHandler(): ProductErrorHandler
    {
        return $this->errorHandler;
    }

    public function buildItems(array $productEntities, string $shopkey, array $customerGroups): array
    {
        if (count($productEntities) === 0) {
            $this->getLogger()->warning('Product could not be found or is not available for search.');
        }

        return parent::buildItems($productEntities, $shopkey, $customerGroups);
    }

    public function buildResponse(array $items, int $start, int $total, array $headers = []): Response
    {
        if (!$this->errorHandler->getExportErrors()->hasErrors()) {
            return parent::buildResponse($items, $start, $total, $headers);
        }

        return $this->buildErrorResponse($this->errorHandler, $headers);
    }

    private function pushErrorHandler(): ProductErrorHandler
    {
        $errorHandler = new ProductErrorHandler();
        $this->getLogger()->pushHandler($errorHandler);

        return $errorHandler;
    }
}
