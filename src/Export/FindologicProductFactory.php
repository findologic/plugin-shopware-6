<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class FindologicProductFactory
{
    /**
     * @param CustomerGroupEntity[] $customerGroups
     *
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoNameException
     * @throws ProductHasNoPricesException
     */
    public function buildInstance(
        ProductEntity $product,
        RouterInterface $router,
        ContainerInterface $container,
        SalesChannelContext $salesChannelContext,
        string $shopkey,
        array $customerGroups,
        Item $item
    ): FindologicProduct {
        return new FindologicProduct(
            $product, $router, $container, $salesChannelContext, $shopkey, $customerGroups, $item);
    }
}
