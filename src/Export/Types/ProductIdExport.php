<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Types;

use FINDOLOGIC\Shopware6Common\Export\Logger\Handler\ProductErrorHandler;
use FINDOLOGIC\Shopware6Common\Export\Types\AbstractExport;
use FINDOLOGIC\Shopware6Common\Export\Adapters\ExportItemAdapter;
use FINDOLOGIC\Shopware6Common\Export\Search\AbstractProductSearcher;
use FINDOLOGIC\Shopware6Common\Export\Services\AbstractDynamicProductGroupService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class ProductIdExport extends XmlExport
{
    private ProductErrorHandler $errorHandler;

    public function __construct(
        AbstractDynamicProductGroupService $dynamicProductGroupService,
        AbstractProductSearcher $productSearcher,
        ExportItemAdapter $exportItemAdapter,
        ContainerInterface $container,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($dynamicProductGroupService, $productSearcher, $exportItemAdapter, $container, $logger);

        $this->errorHandler = $this->pushErrorHandler();
    }

    public function getErrorHandler(): ProductErrorHandler
    {
        return $this->errorHandler;
    }

    public function buildItems(array $products): array
    {
        if (count($products) === 0) {
            $this->logger->warning('Product could not be found or is not available for search.');
        }

        return parent::buildItems($products);
    }

    public function buildResponse(array $items, int $start, int $total, array $headers = []): Response
    {
        if (!$this->errorHandler->getExportErrors()->hasErrors()) {
            return parent::buildResponse($items, $start, $total, $headers);
        }

        return AbstractExport::buildErrorResponse($this->errorHandler, $headers);
    }

    private function pushErrorHandler(): ProductErrorHandler
    {
        $errorHandler = new ProductErrorHandler();
        $this->logger->pushHandler($errorHandler);

        return $errorHandler;
    }
}
