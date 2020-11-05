<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class SalesChannelServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use PluginConfigHelper;

    public function testReturnsTheGivenSalesChannelContextIfIsConfiguredForAllSalesChannels(): void
    {
        $configShopkey = '12341234123412341234123412341234';

        $salesChannelService = $this->getSalesChannelService();
        $currentSalesChannel = $this->buildSalesChannelContext();

        $this->enableFindologicPlugin($this->getContainer(), $configShopkey);
        $salesChannel = $salesChannelService->getSalesChannelContext($currentSalesChannel, $configShopkey);

        $this->assertSame($currentSalesChannel, $salesChannel);
    }

    public function testNothingConfiguredForGivenShopkeyReturnsNull(): void
    {
        $salesChannelService = $this->getSalesChannelService();
        $this->assertNull($salesChannelService->getSalesChannelContext(
            $this->buildSalesChannelContext(),
            '12341234123412341234123412341234'
        ));
    }

    private function getSalesChannelService(): SalesChannelService
    {
        /** @var EntityRepository $configRepository */
        $configRepository = $this->getContainer()->get('system_config.repository');

        return new SalesChannelService(
            $configRepository,
            $this->getContainer()->get(SalesChannelContextFactory::class)
        );
    }
}
