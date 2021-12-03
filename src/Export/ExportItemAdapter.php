<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Export\Adapters\AdapterFactory;
use FINDOLOGIC\FinSearch\Export\Events\ItemAfterAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\ItemBeforeAdaptEvent;
use FINDOLOGIC\FinSearch\Struct\Config;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RouterInterface;

class ExportItemAdapter implements ExportItemAdapterInterface
{
    /** @var ContainerInterface */
    protected $container;

    /** @var RouterInterface */
    protected $router;

    /** @var EventDispatcherInterface|EventDispatcher */
    protected $eventDispatcher;

    /** @var Config */
    protected $config;

    /** @var AdapterFactory */
    protected $adapterFactory;

    /** @var ExportContext */
    protected $exportContext;

    public function __construct(
        ContainerInterface $container,
        RouterInterface $router,
        EventDispatcherInterface $eventDispatcher,
        Config $config,
        AdapterFactory $adapterFactory,
        ExportContext $exportContext
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = $config;
        $this->adapterFactory = $adapterFactory;
        $this->exportContext = $exportContext;
    }

    public function adapt(Item $item, ProductEntity $product): Item
    {
        $this->eventDispatcher->dispatch(new ItemBeforeAdaptEvent($product, $item), ItemBeforeAdaptEvent::NAME);

        $item->setName($this->adapterFactory->getNameAdapter()->adapt($product));
        foreach ($this->adapterFactory->getAttributeAdapter()->adapt($product) as $attribute) {
            $item->addMergedAttribute($attribute);
        }
        $item->setAllPrices($this->adapterFactory->getPriceAdapter()->adapt($product));
        $item->setUrl($this->adapterFactory->getUrlAdapter()->adapt($product));

        $this->eventDispatcher->dispatch(new ItemAfterAdaptEvent($product, $item), ItemAfterAdaptEvent::NAME);

        return $item;
    }
}
