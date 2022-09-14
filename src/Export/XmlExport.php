<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\Export\XML\XMLExporter as XmlFileConverter;
use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Adapters\ExportItemAdapter;
use FINDOLOGIC\FinSearch\Export\Events\AfterItemBuildEvent;
use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class XmlExport extends Export
{
    private const MAXIMUM_PROPERTIES_COUNT = 500;

    private RouterInterface $router;

    private ContainerInterface $container;

    private LoggerInterface $logger;

    private EventDispatcherInterface $eventDispatcher;

    /** @var string[] */
    private array $crossSellingCategories;

    private XmlFileConverter $xmlFileConverter;

    private ExportItemAdapter $exportItemAdapter;

    private ProductSearcher $productSearcher;

    public function __construct(
        RouterInterface $router,
        ContainerInterface $container,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        array $crossSellingCategories = [],
        ?XmlFileConverter $xmlFileConverter = null
    ) {
        $this->router = $router;
        $this->container = $container;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->crossSellingCategories = $crossSellingCategories;
        $this->xmlFileConverter = $xmlFileConverter ?? Exporter::create(Exporter::TYPE_XML);
    }

    /**
     * @param Item[] $items
     */
    public function buildResponse(array $items, int $start, int $total, array $headers = []): Response
    {
        $rawXml = $this->xmlFileConverter->serializeItems(
            $items,
            $start,
            count($items),
            $total
        );

        $response = new Response($rawXml);
        $response->headers->add($headers);

        return $response;
    }

    /**
     * Converts given product entities to Findologic XML items. In case items can not be exported, they won't
     * be returned. Details about why specific products can not be exported, can be found in the logs.
     *
     * @param ProductEntity[] $productEntities
     *
     * @return XMLItem[]
     */
    public function buildItems(array $productEntities): array
    {
        $this->initialize();

        $items = [];
        foreach ($productEntities as $productEntity) {
            $item = $this->exportSingleItem($productEntity);
            if (!$item) {
                continue;
            }

            $this->eventDispatcher->dispatch(new AfterItemBuildEvent($item), AfterItemBuildEvent::NAME);

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @deprecated buildItemsLegacy function will be removed in plugin version 5.0
     *
     * Converts given product entities to Findologic XML items. In case items can not be exported, they won't
     * be returned. Details about why specific products can not be exported, can be found in the logs.
     *
     * @param ProductEntity[] $productEntities
     * @param string $shopkey Required for generating the user group hash.
     * @param CustomerGroupEntity[] $customerGroups
     *
     * @return XMLItem[]
     */
    public function buildItemsLegacy(array $productEntities, string $shopkey, array $customerGroups): array
    {
        $this->initialize();

        $items = [];
        foreach ($productEntities as $productEntity) {
            $item = $this->exportSingleItemLegacy($productEntity, $shopkey, $customerGroups);
            if (!$item) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    private function initialize(): void
    {
        $this->exportItemAdapter = $this->container->get(ExportItemAdapter::class);
        $this->productSearcher = $this->container->get(ProductSearcher::class);
        $this->eventDispatcher = $this->container->get('event_dispatcher');
    }

    private function exportSingleItem(ProductEntity $productEntity): ?Item
    {
        if ($category = $this->getConfiguredCrossSellingCategory($productEntity)) {
            $this->logger->warning(
                sprintf(
                    'Product with id %s (%s) was not exported because it is assigned to cross selling category %s (%s)',
                    $productEntity->getId(),
                    $productEntity->getName(),
                    $category->getId(),
                    implode(' > ', $category->getBreadcrumb())
                ),
                ['product' => $productEntity]
            );

            return null;
        }

        $initialItem = $this->xmlFileConverter->createItem($productEntity->getId());
        $item = $this->exportItemAdapter->adapt($initialItem, $productEntity, $this->logger);

        $pageSize = $this->calculatePageSize($productEntity);
        $iterator = $this->productSearcher->buildVariantIterator($productEntity, $pageSize);

        while (($variantsResult = $iterator->fetch()) !== null) {
            /** @var ProductCollection $variants */
            $variants = $variantsResult->getEntities();
            foreach ($variants->getElements() as $variant) {
                if ($item) {
                    $adaptedItem = $this->exportItemAdapter->adaptVariant($item, $variant);
                } elseif ($adaptedItem = $this->exportItemAdapter->adapt($initialItem, $variant)) {
                    $adaptedItem->setId($variant->getId());
                }

                if ($adaptedItem) {
                    $item = $adaptedItem;
                }
            }
        }

        return $item;
    }

    private function exportSingleItemLegacy(ProductEntity $productEntity, string $shopkey, array $customerGroups): ?Item
    {
        if ($category = $this->getConfiguredCrossSellingCategory($productEntity)) {
            $this->logger->warning(
                sprintf(
                    'Product with id %s (%s) was not exported because it is assigned to cross selling category %s (%s)',
                    $productEntity->getId(),
                    $productEntity->getName(),
                    $category->getId(),
                    implode(' > ', $category->getBreadcrumb())
                ),
                ['product' => $productEntity]
            );

            return null;
        }

        $xmlProduct = new XmlProduct(
            $productEntity,
            $this->router,
            $this->container,
            $shopkey,
            $customerGroups
        );
        $xmlProduct->buildXmlItem($this->logger);

        return $xmlProduct->getXmlItem();
    }

    private function calculatePageSize(ProductEntity $productEntity): int
    {
        $maxPropertiesCount = $this->productSearcher->findMaxPropertiesCount($productEntity);
        if ($maxPropertiesCount >= self::MAXIMUM_PROPERTIES_COUNT) {
            return 1;
        }

        return intval(self::MAXIMUM_PROPERTIES_COUNT / max(1, $maxPropertiesCount));
    }

    private function getConfiguredCrossSellingCategory(ProductEntity $productEntity): ?CategoryEntity
    {
        if (count($this->crossSellingCategories)) {
            $categories = array_merge(
                $this->getAssignedCategories($productEntity),
                $this->getDynamicProductGroupCategories($productEntity)
            );

            foreach ($categories as $categoryId => $category) {
                if (in_array($categoryId, $this->crossSellingCategories)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * @return CategoryEntity[]
     */
    private function getAssignedCategories(ProductEntity $productEntity): array
    {
        return $productEntity->getCategories() ? $productEntity->getCategories()->getElements() : [];
    }

    /**
     * @return CategoryEntity[]
     */
    private function getDynamicProductGroupCategories(ProductEntity $productEntity): array
    {
        if ($this->container->has('fin_search.dynamic_product_group')) {
            /** @var DynamicProductGroupService $dynamicProductGroupService */
            $dynamicProductGroupService = $this->container->get('fin_search.dynamic_product_group');

            if ($dynamicProductGroupService) {
                return $dynamicProductGroupService->getCategories($productEntity->getId());
            }
        }

        return [];
    }
}
