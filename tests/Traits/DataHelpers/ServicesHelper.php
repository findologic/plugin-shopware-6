<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder;
use FINDOLOGIC\FinSearch\Export\Search\ProductSearcher;
use FINDOLOGIC\FinSearch\Utils\Utils;
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
            new CustomerGroupCollection(),
            true,
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
}
