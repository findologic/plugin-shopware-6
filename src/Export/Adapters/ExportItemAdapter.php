<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Export\Events\AfterItemAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\AfterVariantAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\BeforeItemAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\BeforeVariantAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Services\DynamicProductGroupService;
use FINDOLOGIC\Shopware6Common\Export\Adapters\ExportItemAdapter as OriginalExportItemAdapter;
use FINDOLOGIC\Shopware6Common\Export\Logger\ExportExceptionLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity;

class ExportItemAdapter extends OriginalExportItemAdapter
{
    protected EventDispatcherInterface $eventDispatcher;

    private LoggerInterface $logger;

    public function __construct(
        DynamicProductGroupService $dynamicProductGroupService,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        parent::__construct($dynamicProductGroupService);

        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function adaptProduct(Item $item, ProductEntity $product): ?Item
    {
        $this->eventDispatcher->dispatch(new BeforeItemAdaptEvent($product, $item), BeforeItemAdaptEvent::NAME);

        try {
            $item = parent::adaptProduct($item, $product);
        } catch (Throwable $exception) {
            $exceptionLogger = new ExportExceptionLogger($this->logger);
            $exceptionLogger->log($product, $exception);

            return null;
        }

        $this->eventDispatcher->dispatch(new AfterItemAdaptEvent($product, $item), AfterItemAdaptEvent::NAME);

        return $item;
    }

    public function adaptVariant(Item $item, ProductEntity $product): ?Item
    {
        $this->eventDispatcher->dispatch(new BeforeVariantAdaptEvent($product, $item), BeforeVariantAdaptEvent::NAME);

        try {
            parent::adaptVariant($item, $product);
        } catch (Throwable $exception) {
            $exceptionLogger = new ExportExceptionLogger($this->logger);
            $exceptionLogger->log($product, $exception);
            return null;
        }

        $this->eventDispatcher->dispatch(new AfterVariantAdaptEvent($product, $item), AfterVariantAdaptEvent::NAME);

        return $item;
    }
}
