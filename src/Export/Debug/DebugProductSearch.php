<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Debug;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DebugProductSearch
{
    /** @var ContainerInterface */
    private $container;

    /** @var SalesChannelContext|null */
    private $salesChannelContext;

    public function __construct(
        ContainerInterface $container,
        SalesChannelContext $salesChannelContext
    ) {

        $this->container = $container;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getMainProductById(string $productId): ProductEntity
    {
        /** @var ProductEntity $product */
        $product = $this->getProductById($productId);

        return $product->getParentId()
            ? $this->getProductById($product->getParentId())
            : $product;
    }

    public function getProductById(string $productId): ProductEntity
    {
        $criteria = new Criteria([$productId]);

        Utils::addProductAssociations($criteria);
        Utils::addChildrenAssociations($criteria);

        return $this->searchProduct($criteria);
    }

    public function searchProduct(Criteria $criteria): ?ProductEntity
    {
        return $this->getContainer()->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        )->first();
    }

    /**
     * @return ProductEntity[]
     */
    public function getSiblings(string $parentProductId): array
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new EqualsFilter('id', $parentProductId),
                    new EqualsFilter('parentId', $parentProductId),
                ]
            )
        );

        return $this->getContainer()->get('product.repository')->search(
            $criteria,
            $this->getSalesChannelContext()->getContext()
        )->getElements();
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @return SalesChannelContext|null
     */
    public function getSalesChannelContext(): ?SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
