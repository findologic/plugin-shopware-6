<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SearchKeyword;

use FINDOLOGIC\FinSearch\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductSearchBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;

    private SalesChannelContext $salesChannelContext;

    /** @var ProductSearchBuilderInterface|MockObject */
    private $productSearchBuilderMock;

    public function setUp(): void
    {
        $interpreterMock = $this->getMockBuilder(ProductSearchTermInterpreterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $decoratedProductSearchBuilderMock = $this->getMockBuilder(ProductSearchBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productSearchBuilderMock = $this->getMockBuilder(ProductSearchBuilder::class)
            ->setConstructorArgs([
                $interpreterMock,
                $decoratedProductSearchBuilderMock,
                $this->getContainer()->getParameter('kernel.shopware_version')
            ])
            ->onlyMethods(['buildParent', 'buildShopware63AndLower', 'buildShopware64AndGreater'])
            ->getMock();

        $this->salesChannelContext = $this->buildSalesChannelContext(Defaults::SALES_CHANNEL, 'http://test.de');

        parent::setUp();
    }

    public function testSuggestRouteIsIgnoredByFindologic(): void
    {
        // Ensure Shopware handles the request.
        $this->productSearchBuilderMock->expects($this->once())->method('buildParent');
        $this->productSearchBuilderMock->expects($this->never())->method('buildShopware63AndLower');
        $this->productSearchBuilderMock->expects($this->never())->method('buildShopware64AndGreater');

        $request = Request::create('http://your-shop.de/suggest?search=blubbergurken');
        $criteria = new Criteria();
        $this->productSearchBuilderMock->build($request, $criteria, $this->salesChannelContext);
    }

    public function testProductSearchBuildMethodIsUsedAccordingToShopwareVersion(): void
    {
        if (Utils::versionGreaterOrEqual('6.4.0.0')) {
            $this->productSearchBuilderMock->expects($this->never())->method('buildShopware63AndLower');
            $this->productSearchBuilderMock->expects($this->once())->method('buildShopware64AndGreater');
        } else {
            $this->productSearchBuilderMock->expects($this->once())->method('buildShopware63AndLower');
            $this->productSearchBuilderMock->expects($this->never())->method('buildShopware64AndGreater');
        }

        $request = Request::create('http://your-shop.de/search?search=blubbergurken');
        $criteria = new Criteria();
        $this->productSearchBuilderMock->build($request, $criteria, $this->salesChannelContext);
    }
}
