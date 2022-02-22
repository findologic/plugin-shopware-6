<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SearchKeyword;

use FINDOLOGIC\FinSearch\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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

    /** @var SalesChannelContext */
    private $salesChannelContext;

    public function setUp(): void
    {
        $this-> salesChannelContext = $this->buildSalesChannelContext(Defaults::SALES_CHANNEL, 'http://test.de');
        parent::setUp();
    }

    public function testSuggestRouteIsIgnoredByFindologic(): void
    {
        /** @var ProductSearchTermInterpreterInterface|MockObject $interpreterMock */
        $interpreterMock = $this->getMockBuilder(ProductSearchTermInterpreterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $productSearchBuilderMock = $this->getMockBuilder(ProductSearchBuilder::class)
            ->setConstructorArgs([$interpreterMock])
            ->onlyMethods(['buildParent'])
            ->getMock();

        // Ensure Shopware handles the request.
        $productSearchBuilderMock->expects($this->once())->method('buildParent');

        $request = Request::create('http://your-shop.de/suggest?search=blubbergurken');
        $criteria = new Criteria();
        $productSearchBuilderMock->build($request, $criteria, $this->salesChannelContext);
    }

    public function testBuildMethodForShopwareLower64IsUsed(): void
    {
        if (!Utils::versionLowerThan('6.4.0.0')) {
            $this->markTestSkipped('Test ProductSearchBuilder::build for version lower 6.4.0.0');
        }

        /** @var ProductSearchTermInterpreterInterface|MockObject $interpreterMock */
        $interpreterMock = $this->getMockBuilder(ProductSearchTermInterpreterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $productSearchBuilderMock = $this->getMockBuilder(ProductSearchBuilder::class)
            ->setConstructorArgs([$interpreterMock])
            ->onlyMethods(['buildShopware63AndLower', 'buildShopware64AndGreater'])
            ->getMock();

        $productSearchBuilderMock->expects($this->once())->method('buildShopware63AndLower');
        $productSearchBuilderMock->expects($this->never())->method('buildShopware64AndGreater');

        $request = Request::create('http://your-shop.de/suggest?search=blubbergurken');
        $criteria = new Criteria();
        $productSearchBuilderMock->build($request, $criteria, $this->salesChannelContext);
    }

    public function testBuildMethodForShopwareGreater64IsUsed(): void
    {
        if (Utils::versionLowerThan('6.4.0.0')) {
            $this->markTestSkipped('Test ProductSearchBuilder::build for version greater 6.4.0.0');
        }

        /** @var ProductSearchTermInterpreterInterface|MockObject $interpreterMock */
        $interpreterMock = $this->getMockBuilder(ProductSearchTermInterpreterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $productSearchBuilderMock = $this->getMockBuilder(ProductSearchBuilder::class)
            ->setConstructorArgs([$interpreterMock])
            ->onlyMethods(['buildShopware63AndLower', 'buildShopware64AndGreater'])
            ->getMock();

        $productSearchBuilderMock->expects($this->never())->method('buildShopware63AndLower');
        $productSearchBuilderMock->expects($this->once())->method('buildShopware64AndGreater');

        $request = Request::create('http://your-shop.de/suggest?search=blubbergurken');
        $criteria = new Criteria();
        $productSearchBuilderMock->build($request, $criteria, $this->salesChannelContext);
    }
}
