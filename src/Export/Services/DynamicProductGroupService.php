<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Services;

use FINDOLOGIC\FinSearch\Export\Search\CategorySearcher;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Services\AbstractDynamicProductGroupService;
use FINDOLOGIC\Shopware6Common\Export\Validation\OffsetExportConfiguration;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryCollection;

class DynamicProductGroupService extends AbstractDynamicProductGroupService
{
    protected EntityRepository $productRepository;

    protected ProductStreamBuilderInterface $productStreamBuilder;

    protected Context $context;

    protected SalesChannelContext $salesChannelContext;

    protected OffsetExportConfiguration $exportConfig;

    protected ExportContext $exportContext;

    public function __construct(
        EntityRepository $productRepository,
        CategorySearcher $categorySearcher,
        ProductStreamBuilder $productStreamBuilder,
        SalesChannelContext $salesChannelContext,
        OffsetExportConfiguration $exportConfig,
        CacheItemPoolInterface $cache,
        ExportContext $exportContext
    ) {
        $this->productRepository = $productRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->context = $salesChannelContext->getContext();
        $this->salesChannelContext = $salesChannelContext;
        $this->exportConfig = $exportConfig;

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
