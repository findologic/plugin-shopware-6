<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Services;

use FINDOLOGIC\FinSearch\Export\Search\CategorySearcher;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Services\AbstractDynamicProductGroupService;
use FINDOLOGIC\Shopware6Common\Export\Validation\OffsetExportConfiguration;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryCollection;

class DynamicProductGroupService extends AbstractDynamicProductGroupService
{
    protected Context $context;

    public function __construct(
        protected readonly EntityRepository $productRepository,
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly OffsetExportConfiguration $exportConfig,
        CacheItemPoolInterface $cache,
        ExportContext $exportContext,
        CategorySearcher $categorySearcher,
    ) {
        $this->context = $salesChannelContext->getContext();

        parent::__construct($cache, $exportContext, $categorySearcher);
    }

    protected function getProductStreamCategories(): CategoryCollection
    {
        return $this->categorySearcher->getProductStreamCategories(
            $this->exportConfig->getCount(),
            $this->exportConfig->getStart()
        );
    }

    protected function isFirstPage(): bool
    {
        return $this->exportConfig->getStart() === 0;
    }

    protected function isLastPage(): bool
    {
        $currentTotal = $this->exportConfig->getStart() + $this->exportConfig->getCount();

        return $currentTotal >= $this->getDynamicProductGroupsTotal();
    }

    protected function getCurrentOffset(): int
    {
        return $this->exportConfig->getStart();
    }
}
