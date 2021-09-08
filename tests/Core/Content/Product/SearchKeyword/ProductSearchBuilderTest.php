<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SearchKeyword;

use FINDOLOGIC\FinSearch\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class ProductSearchBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;

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
        $salesChannelContext = $this->buildSalesChannelContext(Defaults::SALES_CHANNEL, 'http://test.de');

        $productSearchBuilderMock->build($request, $criteria, $salesChannelContext);
    }
}
