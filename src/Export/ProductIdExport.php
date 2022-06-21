<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\XML\XMLExporter;
use FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
        EventDispatcher $eventDispatcher,
        array $crossSellingCategories = [],
        ?XMLExporter $xmlFileConverter = null
    ) {
        parent::__construct($router, $container, $logger, $eventDispatcher, $crossSellingCategories, $xmlFileConverter);

        $this->errorHandler = $this->pushErrorHandler();
    }

    public function getErrorHandler(): ProductErrorHandler
    {
        return $this->errorHandler;
    }

    public function buildItems(array $productEntities): array
    {
        if (count($productEntities) === 0) {
            $this->getLogger()->warning('Product could not be found or is not available for search.');
        }

        return parent::buildItems($productEntities);
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
