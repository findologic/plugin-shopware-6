<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Export\Search\CategorySearcher;
use FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder;
use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Config\ImplementationType;
use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Vin\ShopwareSdk\Data\Entity\Category\CategoryEntity as SdkCategoryEntity;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupCollection;
use Vin\ShopwareSdk\Data\Entity\SalesChannel\SalesChannelEntity;

trait ServicesHelper
{
    public function getExportContext(
        SalesChannelContext $salesChannelContext,
        CategoryEntity $navigationCategory,
        ?CustomerGroupCollection $customerGroupCollection = null,
        ?string $shopkey = 'ABCDABCDABCDABCDABCDABCDABCDABCD'
    ): ExportContext {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = Utils::createSdkEntity(
            SalesChannelEntity::class,
            $salesChannelContext->getSalesChannel()
        );

        /** @var SdkCategoryEntity $sdkNavigationCategory */
        $sdkNavigationCategory = Utils::createSdkEntity(
            SdkCategoryEntity::class,
            $navigationCategory,
        );

        return new ExportContext(
            $shopkey,
            $salesChannel,
            $sdkNavigationCategory,
            $customerGroupCollection ?? new CustomerGroupCollection(),
            true,
            ImplementationType::PLUGIN
        );
    }

    public function getPluginConfig(?array $overrides = []): PluginConfig
    {
        return PluginConfig::createFromArray(array_merge([
            'shopkey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
            'active' => true
        ], $overrides));
    }

    public function getProductSearcher(
        SalesChannelContext $salesChannelContext,
        ContainerInterface $container,
        ProductCriteriaBuilder $productCriteriaBuilder,
        ExportContext $exportContext,
        ?array $configOverrides = []
    ): ProductSearcher {
        return new ProductSearcher(
            $salesChannelContext,
            $container->get('product.repository'),
            $productCriteriaBuilder,
            $exportContext,
            $this->getPluginConfig($configOverrides)
        );
    }

    public function getCategorySearcher(
        SalesChannelContext $salesChannelContext,
        ContainerInterface $container,
        ExportContext $exportContext
    ): CategorySearcher {
        return new CategorySearcher(
            $salesChannelContext,
            $container->get('category.repository'),
            $exportContext,
        );
    }
}
