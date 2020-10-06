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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;

trait ExportHelper
{
    public function getDefaultSalesChannelContextMock(): SalesChannelContext
    {
        $salesChannelId = Defaults::SALES_CHANNEL;
        $navigationCategoryId = $this->getNavigationCategoryId();

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $salesChannelMock = $this->getMockBuilder(SalesChannelEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelMock->method('getId')->willReturn($salesChannelId);
        $salesChannelMock->method('getNavigationCategoryId')->willReturn($navigationCategoryId);

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
            ->addMethods(['set'])
            ->onlyMethods(['get', 'has'])
            ->getMock();

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getDefaultSalesChannelContextMock();

        /** @var SalesChannelEntity|MockObject $salesChannelMock */
        $systemConfigRepositoryMock = $this->getSystemConfigRepositoryMock();

        $defaultServicesMap = [
            ['translator', $this->getContainer()->get('translator')],
            ['system_config.repository', $systemConfigRepositoryMock],
            ['customer_group.repository', $this->getContainer()->get('customer_group.repository')],
            ['order_line_item.repository', $this->getContainer()->get('order_line_item.repository')],
            ['fin_search.sales_channel_context', $salesChannelContextMock],
            [FindologicProductFactory::class, new FindologicProductFactory()]
        ];

        foreach ($services as $key => $service) {
            $defaultServicesMap[] = [$key, $service];
        }

        $containerMock->method('get')->willReturnMap($defaultServicesMap);

        return $containerMock;
    }

    /**
     * @param SystemConfigEntity|MockObject|null $systemConfigEntity
     * @return EntityRepository
     */
    public function getSystemConfigRepositoryMock(?SystemConfigEntity $systemConfigEntity = null): EntityRepository
    {
        /** @var EntityRepository|MockObject $systemConfigRepositoryMock */
        $systemConfigRepositoryMock = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        if (!$systemConfigEntity) {
            /** @var SystemConfigEntity|MockObject $systemConfigEntity */
            $systemConfigEntity = $this->getMockBuilder(SystemConfigEntity::class)->getMock();
            $systemConfigEntity->expects($this->once())
                ->method('getConfigurationValue')
                ->willReturn($this->validShopkey);
            $systemConfigEntity->expects($this->once())->method('getSalesChannelId')->willReturn(null);
        }

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

    public function getNavigationCategoryId(): string
    {
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        return $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
    }
}
