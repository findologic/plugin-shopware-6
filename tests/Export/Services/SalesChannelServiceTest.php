<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\Services\SalesChannelService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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
        $currentSalesChannel = $this->buildAndCreateSalesChannelContext();

        $this->enableFindologicPlugin($this->getContainer(), $configShopkey, $currentSalesChannel);
        $salesChannel = $salesChannelService->getSalesChannelContext($currentSalesChannel, $configShopkey);

        $this->assertEquals($currentSalesChannel, $salesChannel);
    }

    public function testNothingConfiguredForGivenShopkeyReturnsNull(): void
    {
        $salesChannelService = $this->getSalesChannelService();
        $this->assertNull($salesChannelService->getSalesChannelContext(
            $this->buildAndCreateSalesChannelContext(),
            '12341234123412341234123412341234'
        ));
    }

    private function getSalesChannelService(): SalesChannelService
    {
        /** @var EntityRepository $configRepository */
        $configRepository = $this->getContainer()->get('finsearch_config.repository');

        return new SalesChannelService(
            $configRepository,
            $this->getContainer()->get(SalesChannelContextFactory::class),
            $this->getContainer()->get(RequestTransformerInterface::class)
        );
    }

    public function testCorrectLanguageIdIsConfiguredInSalesChannelContext(): void
    {
        $languageId = Uuid::randomHex();
        $this->createLanguage($languageId);

        $configShopkey = '12341234123412341234123412341234';

        $salesChannelService = $this->getSalesChannelService();
        $currentSalesChannel = $this->buildAndCreateSalesChannelContext(
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'http://test.de',
            null,
            $languageId
        );

        $this->enableFindologicPlugin($this->getContainer(), $configShopkey, $currentSalesChannel);
        $salesChannel = $salesChannelService->getSalesChannelContext($currentSalesChannel, $configShopkey);

        $this->assertSame($languageId, $salesChannel->getSalesChannel()->getLanguageId());
    }
}
