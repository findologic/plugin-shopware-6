<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;

trait ExportHelper
{
    public function getDefaultSalesChannelContextMock(): SalesChannelContext
    {
        $salesChannelId = Defaults::SALES_CHANNEL;

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);

        $salesChannelContextMock->expects($this->any())
            ->method('getContext')
            ->willReturn($this->defaultContext);

        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelMock);

        return $salesChannelContextMock;
    }

    /**
     * @param mixed[] $services Service mapping array for dependency injection for the container. It can be either an
     * actual service or a mock implementation. Use service name as key and implementation as value.
     * e.g <code>['product.repository' => $customRepositoryMock]</code>
     */
    public function getContainerMock(array $services = []): PsrContainerInterface
    {
        /** @var PsrContainerInterface|MockObject $containerMock */
        $containerMock = $this->getMockBuilder(PsrContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $systemConfigRepositoryMock = $this->getSystemConfigRepositoryMock();
        $defaultServicesMap = [
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['order_line_item.repository', $this->getContainer()->get('order_line_item.repository')],
            [FindologicProductFactory::class, new FindologicProductFactory()]
        ];

        foreach ($services as $key => $service) {
            $defaultServicesMap[] = [$key, $service];
        }

        $containerMock->method('get')->willReturnMap($defaultServicesMap);

        return $containerMock;
    }

    public function getSystemConfigRepositoryMock(): EntityRepository
    {
        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SystemConfigEntity|MockObject $systemConfigEntity */
        $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
        $systemConfigEntity->expects($this->once())
            ->method('getConfigurationValue')
            ->willReturn($this->validShopkey);
        $systemConfigEntity->expects($this->once())->method('getSalesChannelId')->willReturn(null);

        /** @var SystemConfigCollection $systemConfigCollection */
        $systemConfigCollection = new SystemConfigCollection([$systemConfigEntity]);

        /** @var EntitySearchResult $systemConfigEntitySearchResult */
        $systemConfigEntitySearchResult = new EntitySearchResult(
            1,
            $systemConfigCollection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $systemConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($systemConfigEntitySearchResult);

        return $systemConfigRepositoryMock;
    }
}
