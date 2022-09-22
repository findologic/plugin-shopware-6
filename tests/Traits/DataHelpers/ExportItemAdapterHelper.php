<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Export\Services\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\CatUrlBuilderService;
use FINDOLOGIC\Shopware6Common\Export\Adapters\AdapterFactory;
use FINDOLOGIC\Shopware6Common\Export\Adapters\AttributeAdapter;
use FINDOLOGIC\Shopware6Common\Export\Adapters\ExportItemAdapter;
use FINDOLOGIC\Shopware6Common\Export\Config\PluginConfig;
use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use Monolog\Logger;

trait ExportItemAdapterHelper
{
    public function getExportItemAdapter(PluginConfig $config): ExportItemAdapter
    {
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $attributeAdapter = new AttributeAdapter(
            $this->getContainer()->get(DynamicProductGroupService::class),
            $this->getContainer()->get(CatUrlBuilderService::class),
            $this->getContainer()->get(ExportContext::class),
            $config ?? $this->getContainer()->get(PluginConfig::class),
        );

        $adapterFactoryMock = $this->getMockBuilder(AdapterFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $adapterFactoryMock->expects($this->any())
            ->method('getAttributeAdapter')
            ->willReturn($attributeAdapter);

        return new ExportItemAdapter(
            $adapterFactoryMock,
            $loggerMock,
            $this->getContainer()->get('event_dispatcher'),
        );
    }
}
