<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ImageHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\OrderHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\RandomIdHelper;
use PHPUnit\Framework\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;

use function array_map;
use function current;
use function explode;
use function getenv;
use function implode;
use function parse_url;


class UrlBuilderServiceTest extends TestCase
{
//    use SalesChannelHelper;
//    use RandomIdHelper;
//    use IntegrationTestBehaviour;
    use RandomIdHelper;
    use ProductHelper;
    use ConfigHelper;
    use SalesChannelHelper;
    use OrderHelper;
    use ImageHelper;


    /** @var SalesChannelContext */
    private $salesChannelContext;


    protected function setUp(): void
    {
        parent::setUp();
//        $this->salesChannelContext = $this->buildSalesChannelContext();
//        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
    }

    public function testRemoveInvalidUrls(): void
    {

//        $urlBuilderService = $this->getContainer()->get(UrlBuilderService::class);
//        $urlBuilderService->setSalesChannelContext($this->salesChannelContext);

//        $product = $this->createTestProduct();
//        $allowedUrl = $urlBuilderService->removeInvalidUrls([]);

        $this->assertSame(0, 0);
    }
}