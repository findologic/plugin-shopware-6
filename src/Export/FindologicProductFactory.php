<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Struct\FindologicProduct;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\Routing\RouterInterface;

class FindologicProductFactory
{
    /**
     * @throws ProductHasNoCategoriesException
     * @throws ProductHasNoPricesException
     */
    public function buildInstance(
        ProductEntity $product,
        RouterInterface $router,
        ContainerInterface $container,
        Context $context,
        string $shopkey,
        array $customerGroups
    ): FindologicProduct {
        return new FindologicProduct($product, $router, $container, $context, $shopkey, $customerGroups);
    }
}
