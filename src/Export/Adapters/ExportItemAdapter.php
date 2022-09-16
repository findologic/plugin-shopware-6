<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\Events\AfterItemAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\AfterVariantAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\BeforeItemAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\BeforeVariantAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Logger\ExportExceptionLogger;
use FINDOLOGIC\Shopware6Common\Export\Adapters\ExportItemAdapter as OriginalExportItemAdapter;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

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

    /**
     * @param ProductEntity $product
     */
    public function adaptProduct(Item $item, $product): ?Item
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

    /**
     * @param ProductEntity $product
     */
    public function adaptVariant(Item $item, $product): ?Item
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
