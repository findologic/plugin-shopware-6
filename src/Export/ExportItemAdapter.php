<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use Exception;
use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Exceptions\EmptyValueNotAllowedException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductInvalidException;
use FINDOLOGIC\FinSearch\Export\Adapters\AdapterFactory;
use FINDOLOGIC\FinSearch\Export\Events\AfterItemAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\BeforeItemAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Logger\ExportExceptionLogger;
use FINDOLOGIC\FinSearch\Struct\Config;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

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

    /** @var LoggerInterface  */
    private $logger;

    public function __construct(
        ContainerInterface $container,
        RouterInterface $router,
        EventDispatcherInterface $eventDispatcher,
        Config $config,
        AdapterFactory $adapterFactory,
        ExportContext $exportContext,
        LoggerInterface $logger
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = $config;
        $this->adapterFactory = $adapterFactory;
        $this->exportContext = $exportContext;
        $this->logger = $logger;
    }

    public function adapt(Item $item, ProductEntity $product): ?Item
    {
        $this->eventDispatcher->dispatch(new BeforeItemAdaptEvent($product, $item), BeforeItemAdaptEvent::NAME);

        try {
            $item = $this->adaptProduct($item, $product);
        } catch (EmptyValueNotAllowedException | ProductInvalidException | Exception | Throwable $exception) {
            $exceptionLogger = new ExportExceptionLogger($this->logger);
            $exceptionLogger->log($product, $exception);

            return null;
        }

        $this->eventDispatcher->dispatch(new AfterItemAdaptEvent($product, $item), AfterItemAdaptEvent::NAME);

        return $item;
    }

    /**
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    protected function adaptProduct(Item $item, ProductEntity $product): Item
    {
        $item->setName($this->adapterFactory->getNameAdapter()->adapt($product));

        foreach ($this->adapterFactory->getAttributeAdapter()->adapt($product) as $attribute) {
            $item->addMergedAttribute($attribute);
        }

        $item->setAllPrices($this->adapterFactory->getPriceAdapter()->adapt($product));
        $item->setUrl($this->adapterFactory->getUrlAdapter()->adapt($product));

        if ($description = $this->adapterFactory->getDescriptionAdapter()->adapt($product)) {
            $item->setDescription($description);
        }

        if ($dateAdded = $this->adapterFactory->getDateAddedAdapter()->adapt($product)) {
            $item->setDateAdded($dateAdded);
        }

        foreach ($this->adapterFactory->getKeywordsAdapter()->adapt($product) as $keyword) {
            $item->addKeyword($keyword);
        }

        foreach ($this->adapterFactory->getImagesAdapter()->adapt($product) as $image) {
            $item->addImage($image);
        }

        if ($salesFrequency = $this->adapterFactory->getSalesFrequencyAdapter()->adapt($product)) {
            $item->setSalesFrequency($salesFrequency);
        }

        foreach ($this->adapterFactory->getUserGroupsAdapter()->adapt($product) as $userGroup) {
            $item->addUsergroup($userGroup);
        }

        foreach ($this->adapterFactory->getOrderNumbersAdapter()->adapt($product) as $orderNumber) {
            $item->addOrdernumber($orderNumber);
        }

        foreach ($this->adapterFactory->getPropertiesAdapter()->adapt($product) as $property) {
            $item->addProperty($property);
        }

        return $item;
    }
}
