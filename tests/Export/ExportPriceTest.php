<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\XmlExport;
use FINDOLOGIC\FinSearch\Findologic\Config\FinSearchConfigEntity;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Monolog\Logger;

/**
 * @method ContainerInterface getContainer()
 */
class ExportPriceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;
    use SalesChannelHelper;

    /** @var FinSearchConfigEntity */
    private $finsearchConfigEntity;

    /**
     * @method ContainerInterface getContainer();
     */

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var Logger */
    protected $logger;


    public function createCurrency(): string
    {
        $context = Context::createDefaultContext();

        $currencyId = Uuid::randomHex();

        $cashRoundingConfig = [
            'decimals' => 2,
            'interval' => 1,
            'roundForNet' => false
        ];

        /** @var EntityRepositoryInterface $currencyRepo */
        $currencyRepo = $this->getContainer()->get('currency.repository');
        $currencyRepo->upsert(
            [
                [
                    'id' => $currencyId,
                    'isoCode' => 'FDL',
                    'factor' => 0.5,
                    'symbol' => 'F',
                    'decimalPrecision' => 2,
                    'name' => 'Findologic Currency',
                    'shortName' => 'FL',
                    'itemRounding' => $cashRoundingConfig,
                    'totalRounding' => $cashRoundingConfig,
                ]
            ],
            $context
        );

        return $currencyId;
    }

    protected function setup(): void
    {
        $this->crossSellCategories = ["2221211212121121212122121121212"];
        $this->logger = new Logger('fl_test_logger');
    }

    protected function getExport(): XmlExport
    {
        /** @var Router $router */
        $router = $this->getContainer()->get(Router::class);

        return new XmlExport(
            $router,
            $this->getContainer(),
            $this->logger,
            $this->crossSellCategories
        );
    }

    public function testNewCurrencyIsHalfToDefaultCurrency()
    {
        $currencyId = $this->createCurrency();
        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->salesChannelContext->getSalesChannel()->setCurrencyId($currencyId);
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $testProduct = $this->createTestProduct([
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]
            ]
        ]);
        $shopKey = '286DCC326488BE6165863587EBD162F8';
        $items = $this->getExport()->buildItems([$testProduct], $shopKey, []);
        $productId = $items[0]->getId();

        try {
            $criteria = new Criteria([$productId]);
            $criteria = Utils::addProductAssociations($criteria);
            $criteria->addAssociation('visibilities');
            $item = $this->getContainer()->get('product.repository')
                ->search($criteria, Context::createDefaultContext())->get($productId);
        } catch (InconsistentCriteriaIdsException $e) {
            return null;
        }

        $defaultSalesChannelCurrency = $this->salesChannelContext->
        getSalesChannel()->getCurrencyId();

        $defaultCurrencyGrossPrice = $item->getPrice()->
        getCurrencyPrice($defaultSalesChannelCurrency)->getGross();
        $newCurrencyGrossPrice = $item->getPrice()->getCurrencyPrice($defaultSalesChannelCurrency)->getGross();


        $this->assertSame($defaultCurrencyGrossPrice * 0.5, $newCurrencyGrossPrice);
    }
}
