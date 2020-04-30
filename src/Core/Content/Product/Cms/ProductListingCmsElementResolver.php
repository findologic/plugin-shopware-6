<?php

namespace FINDOLOGIC\FinSearch\Core\Content\Product\Cms;

use Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver as ShopwareProductListingCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGatewayInterface;

class ProductListingCmsElementResolver extends ShopwareProductListingCmsElementResolver
{
    /**
     * @var ProductListingGatewayInterface
     */
    private $listingGateway;

    public function __construct(ProductListingGatewayInterface $listingGateway)
    {
        parent::__construct($listingGateway);

        $this->listingGateway = $listingGateway;
    }
}
