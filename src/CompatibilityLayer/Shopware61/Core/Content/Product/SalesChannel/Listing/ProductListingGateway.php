<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\CompatibilityLayer\Shopware61\Core\Content\Product\SalesChannel\Listing;

use FINDOLOGIC\FinSearch\CompatibilityLayer\Shopware61\Core\Content\Product\SalesChannel\FindologicProductListingGateway;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGateway as ShopwareProductListingGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingGateway extends ShopwareProductListingGateway
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($productRepository, $eventDispatcher);
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function search(Request $request, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        $findologicProductListingSearchGateway = FindologicProductListingGateway::getInstance(
            FindologicProductListingGateway::TYPE_NAVIGATION,
            $this->productRepository,
            $this->eventDispatcher
        );

        return $findologicProductListingSearchGateway->search($request, $salesChannelContext);
    }
}
