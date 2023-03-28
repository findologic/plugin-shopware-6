<?php

declare(strict_types=1);

namespace FINDOLOGIC\Tests\Findologic\Request\Parser;

use FINDOLOGIC\FinSearch\Findologic\Request\Parser\NavigationCategoryParser;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Symfony\Component\HttpFoundation\Request;

class NavigationCategoryParserTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;

    /** @var GenericPageLoader|MockObject */
    private $genericPageLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->genericPageLoader = $this->getContainer()->get(GenericPageLoader::class);
    }

    public function testFindsCategoryFromRequest(): void
    {
        $expectedCategory = $this->getCategory();
        $request = new Request(['navigationId' => $expectedCategory->getId()]);
        $salesChannelContext = $this->buildAndCreateSalesChannelContext();

        $category = $this->getDefaultNavigationCategoryParser()->parse($request, $salesChannelContext);

        $this->assertEquals($expectedCategory, $category);
    }

    public function testFindsCategoryFromPage(): void
    {
        $expectedCategory = $this->getCategory();
        $this->genericPageLoader = $this->getMockBuilder(GenericPageLoader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $navigationTreeMock = $this->getMockBuilder(Tree::class)
            ->disableOriginalConstructor()
            ->getMock();
        $navigationTreeMock->expects($this->any())->method('getActive')->willReturn($expectedCategory);

        /** @var HeaderPagelet|MockObject $headerPageletMock */
        $headerPageletMock = $this->getMockBuilder(HeaderPagelet::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headerPageletMock->expects($this->any())->method('getNavigation')->willReturn($navigationTreeMock);

        $page = new Page();
        $page->setHeader($headerPageletMock);

        $request = new Request();
        $salesChannelContext = $this->buildAndCreateSalesChannelContext();
        $category = $this->getDefaultNavigationCategoryParser()->parse($request, $salesChannelContext);

        $this->assertEquals($expectedCategory, $category);
    }

    public function testSalesChannelContextIsNotOverriddenByInternalPageRequest(): void
    {
        $expectedCategory = $this->getCategory();
        $this->genericPageLoader = $this->getMockBuilder(GenericPageLoader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $navigationTreeMock = $this->getMockBuilder(Tree::class)
            ->disableOriginalConstructor()
            ->getMock();
        $navigationTreeMock->expects($this->any())->method('getActive')->willReturn($expectedCategory);

        /** @var HeaderPagelet|MockObject $headerPageletMock */
        $headerPageletMock = $this->getMockBuilder(HeaderPagelet::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headerPageletMock->expects($this->any())->method('getNavigation')->willReturn($navigationTreeMock);

        $page = new Page();
        $page->setHeader($headerPageletMock);

        $request = new Request();
        $salesChannelContext = $this->buildAndCreateSalesChannelContext();

        $category = $this->getDefaultNavigationCategoryParser()->parse($request, $salesChannelContext);

        $this->assertEquals($expectedCategory, $category);
    }

    private function getCategory(): CategoryEntity
    {
        $categoryRepo = $this->getContainer()->get('category.repository');
        $categories = $categoryRepo->search(new Criteria(), Context::createDefaultContext());

        /** @var CategoryEntity $expectedCategory */
        return $categories->first();
    }

    private function getDefaultNavigationCategoryParser(): NavigationCategoryParser
    {
        return new NavigationCategoryParser(
            $this->getContainer()->get('category.repository')
        );
    }
}
