<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use DateTime;
use FINDOLOGIC\FinSearch\Export\Adapters\DateAddedAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DateAddedAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function testDateAddedIsBasedOnReleaseDate(): void
    {
        $releaseDate = DateTime::createFromFormat(DATE_ATOM, '2021-11-09T16:00:00+00:00');
        $adapter = $this->getContainer()->get(DateAddedAdapter::class);
        $product = $this->createTestProduct();
        $product->setReleaseDate($releaseDate);

        $dateAdded = $adapter->adapt($product);

        $this->assertSame($releaseDate->format(DATE_ATOM), $dateAdded->getValues()['']);
    }
}
