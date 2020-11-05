<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\Export\XML\XMLExporter as XmlFileConverter;
use FINDOLOGIC\Export\XML\XMLItem;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class XmlExport extends Export
{
    /** @var RouterInterface */
    private $router;

    /** @var ContainerInterface */
    private $container;

    /** @var LoggerInterface */
    private $logger;

    /** @var string[] */
    private $crossSellingCategories;

    /** @var XmlFileConverter */
    private $xmlFileConverter;

    public function __construct(
        RouterInterface $router,
        ContainerInterface $container,
        LoggerInterface $logger,
        array $crossSellingCategories = [],
        ?XmlFileConverter $xmlFileConverter = null
    ) {
        $this->router = $router;
        $this->container = $container;
        $this->logger = $logger;
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
     * @param string $shopkey Required for generating the user group hash.
     * @param CustomerGroupEntity[] $customerGroups
     *
     * @return XMLItem[]
     */
    public function buildItems(
        array $productEntities,
        string $shopkey,
        array $customerGroups
    ): array {
        $items = [];
        foreach ($productEntities as $productEntity) {
            $item = $this->exportSingleItem($productEntity, $shopkey, $customerGroups);
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

    private function exportSingleItem(
        ProductEntity $productEntity,
        string $shopkey,
        array $customerGroups
    ): ?Item {
        if ($this->isProductInCrossSellingCategory($productEntity)) {
            $category = $productEntity->getCategories()->first();
            $this->logger->warning(sprintf(
                'Product with id %s (%s) was not exported because it ' .
                'is assigned to cross selling category %s (%s)',
                $productEntity->getId(),
                $productEntity->getName(),
                $category->getId(),
                implode(' > ', $category->getBreadcrumb())
            ), ['product' => $productEntity]);

            return null;
        }

        $xmlProduct = new XmlProduct(
            $productEntity,
            $this->router,
            $this->container,
            $this->container->get('fin_search.sales_channel_context')->getContext(),
            $shopkey,
            $customerGroups
        );
        $xmlProduct->buildXmlItem($this->logger);

        return $xmlProduct->getXmlItem();
    }

    private function isProductInCrossSellingCategory(ProductEntity $productEntity): bool
    {
        if (!empty($this->crossSellingCategories)) {
            $categories = $productEntity->getCategories();
            $category = $categories ? $categories->first() : null;
            $categoryId = $category ? $category->getId() : null;

            return (in_array($categoryId, $this->crossSellingCategories));
        }

        return false;
    }
}
