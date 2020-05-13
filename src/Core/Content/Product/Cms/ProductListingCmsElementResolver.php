<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\Cms;

use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductListingCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @var ProductListingRoute
     */
    private $listingGateway;

    public function __construct(ProductListingRoute $listingGateway)
    {
        $this->listingGateway = $listingGateway;
    }

    public function getType(): string
    {
        return 'product-listing';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new ProductListingStruct();
        $slot->setData($data);

        $categoryId = $this->getNavigationId(
            $resolverContext->getRequest(),
            $resolverContext->getSalesChannelContext()
        );
        $listing = $this->listingGateway->load(
            $categoryId,
            $resolverContext->getRequest(),
            $resolverContext->getSalesChannelContext()
        );

        $data->setListing($listing->getResult());
    }

    private function getNavigationId(Request $request, SalesChannelContext $salesChannelContext): string
    {
        $params = $request->attributes->get('_route_params');

        if ($params && isset($params['navigationId'])) {
            return $params['navigationId'];
        }

        return $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
    }
}
