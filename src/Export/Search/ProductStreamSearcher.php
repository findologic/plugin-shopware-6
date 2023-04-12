<?php

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\Shopware6Common\Export\Search\AbstractProductStreamSearcher;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductStreamSearcher extends AbstractProductStreamSearcher
{
    public function __construct(
        protected readonly ProductStreamBuilder $productStreamBuilder,
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly SalesChannelRepository $salesChannelProductRepository
    ) {
    }

    public function isProductInDynamicProductGroup(string $productId, string $streamId): bool
    {
        $filters = $this->productStreamBuilder->buildFilters($streamId, $this->salesChannelContext->getContext());

        $criteria = new Criteria([$productId]);
        $criteria->addFilter(...$filters);

        return !!$this->salesChannelProductRepository->searchIds($criteria, $this->salesChannelContext)->firstId();
    }
}
