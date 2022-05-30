<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Export\Adapters\AdapterFactory;
use FINDOLOGIC\FinSearch\Export\Adapters\AttributeAdapter;
use FINDOLOGIC\FinSearch\Export\ExportItemAdapter;
use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use FINDOLOGIC\FinSearch\Struct\Config;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Monolog\Logger;

trait ExportItemAdapterHelper
{
    public function getExportItemAdapter(Config $config): ExportItemAdapter
    {
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $attributeAdapter = new AttributeAdapter(
            $config,
            $this->getContainer()->get('fin_search.dynamic_product_group'),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get('fin_search.sales_channel_context'),
            $this->getContainer()->get(UrlBuilderService::class),
            $this->getContainer()->get('fin_search.export_context')
        );

        $adapterFactoryMock = $this->getMockBuilder(AdapterFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $adapterFactoryMock->expects($this->once())
            ->method('getAttributeAdapter')
            ->willReturn($attributeAdapter);

        return new ExportItemAdapter(
            $this->getContainer()->get('service_container'),
            $this->getContainer()->get('router'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('FINDOLOGIC\FinSearch\Struct\Config'),
            $adapterFactoryMock,
            $this->getContainer()->get('fin_search.export_context'),
            $loggerMock
        );
    }
}
