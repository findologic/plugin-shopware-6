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
use FINDOLOGIC\FinSearch\Export\Events\AfterVariantAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\BeforeItemAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Events\BeforeVariantAdaptEvent;
use FINDOLOGIC\FinSearch\Export\Logger\ExportExceptionLogger;
use FINDOLOGIC\FinSearch\Struct\Config;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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

    /** @var AdapterFactory  $adapterFactory*/
    protected $adapterFactory;

    /** @var ExportContext $exportContext*/
    protected $exportContext;

    /** @var LoggerInterface  $logger*/
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

    public function adapt(Item $item, ProductEntity $product, ?LoggerInterface $logger = null): ?Item
    {
        $this->eventDispatcher->dispatch(new BeforeItemAdaptEvent($product, $item), BeforeItemAdaptEvent::NAME);

        try {
            $item = $this->adaptProduct($item, $product);
        } catch (Throwable $exception) {
            $exceptionLogger = new ExportExceptionLogger($logger ?: $this->logger);
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
            foreach ($this->adapterFactory->getOrderNumbersAdapter()->adapt($product) as $orderNumber) {
                $item->addOrdernumber($orderNumber);
            }

            foreach ($this->adapterFactory->getAttributeAdapter()->adapt($product) as $attribute) {
                $item->addMergedAttribute($attribute);
            }

            foreach ($this->adapterFactory->getShopwarePropertiesAdapter()->adapt($product) as $property) {
                $item->addProperty($property);
            }
        } catch (Throwable $exception) {
            $exceptionLogger = new ExportExceptionLogger($this->logger);
            $exceptionLogger->log($product, $exception);
            return null;
        }

        $this->eventDispatcher->dispatch(new AfterVariantAdaptEvent($product, $item), AfterVariantAdaptEvent::NAME);

        return $item;
    }

    /**
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    protected function adaptProduct(Item $item, ProductEntity $product): Item
    {
        foreach ($this->adapterFactory->getAttributeAdapter()->adapt($product) as $attribute) {
            $item->addMergedAttribute($attribute);
        }

        if ($bonus = $this->adapterFactory->getBonusAdapter()->adapt($product)) {
            $item->setBonus($bonus);
        }

        if ($dateAdded = $this->adapterFactory->getDateAddedAdapter()->adapt($product)) {
            $item->setDateAdded($dateAdded);
        }

        if ($description = $this->adapterFactory->getDescriptionAdapter()->adapt($product)) {
            $item->setDescription($description);
        }

        foreach ($this->adapterFactory->getImagesAdapter()->adapt($product) as $image) {
            $item->addImage($image);
        }

        foreach ($this->adapterFactory->getKeywordsAdapter()->adapt($product) as $keyword) {
            $item->addKeyword($keyword);
        }

        if ($name = $this->adapterFactory->getNameAdapter()->adapt($product)) {
            $item->setName($name);
        }

        foreach ($this->adapterFactory->getOrderNumbersAdapter()->adapt($product) as $orderNumber) {
            $item->addOrdernumber($orderNumber);
        }

        $item->setAllPrices($this->adapterFactory->getPriceAdapter()->adapt($product));

        foreach ($this->adapterFactory->getPropertiesAdapter()->adapt($product) as $property) {
            $item->addProperty($property);
        }

        if ($salesFrequency = $this->adapterFactory->getSalesFrequencyAdapter()->adapt($product)) {
            $item->setSalesFrequency($salesFrequency);
        }

        if ($sort = $this->adapterFactory->getSortAdapter()->adapt($product)) {
            $item->setSort($sort);
        }

        if ($summary = $this->adapterFactory->getSummaryAdapter()->adapt($product)) {
            $item->setSummary($summary);
        }

        if ($url = $this->adapterFactory->getUrlAdapter()->adapt($product)) {
            $item->setUrl($url);
        }

        foreach ($this->adapterFactory->getUserGroupsAdapter()->adapt($product) as $userGroup) {
            $item->addUsergroup($userGroup);
        }

        return $item;
    }
}
