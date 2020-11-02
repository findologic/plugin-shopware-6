<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\SalesChannelService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SalesChannelServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;

    public function testReturnsTheGivenSalesChannelContextIfIsConfiguredForAllSalesChannels(): void
    {
        $configShopkey = '12341234123412341234123412341234';

        $salesChannelService = $this->getSalesChannelService();
        $currentSalesChannel = $this->buildSalesChannelContext();

        $this->enableFindologicInPluginConfiguration($configShopkey, null);
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

    protected function enableFindologicInPluginConfiguration(
        ?string $shopkey = null,
        ?SalesChannelContext $salesChannelContext = null
    ): void {
        $configService = $this->getContainer()->get(SystemConfigService::class);
        $configService->set(
            'FinSearch.config.active',
            true,
            $salesChannelContext ? $salesChannelContext->getSalesChannel()->getId() : null
        );
        $configService->set(
            'FinSearch.config.shopkey',
            $shopkey,
            $salesChannelContext ? $salesChannelContext->getSalesChannel()->getId() : null
        );
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
