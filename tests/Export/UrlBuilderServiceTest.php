<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class UrlBuilderServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;
    use SalesChannelHelper;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var RouterInterface|MockObject */
    private $urlBuilderService;

    protected function setUp(): void
    {
        parent::setUp();

        $routerMock = $this->getMockBuilder(RouterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var EntityRepository $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');
        $this->salesChannelContext = $this->buildSalesChannelContext();

        $this->urlBuilderService = new UrlBuilderService($routerMock, $categoryRepository);
        $this->urlBuilderService->setSalesChannelContext($this->salesChannelContext);
    }

    public function testRemoveInvalidUrls(): void
    {
        $product = $this->createTestProduct();
        $allowedUrl = $this->urlBuilderService->removeInvalidUrls($product->getSeoUrls()->getElements());

        $this->assertSame(2, $allowedUrl->count());
    }

    public function testGetProductSeoPath(): void
    {
        $product = $this->createTestProduct();
        $seoUrl = $this->urlBuilderService->getProductSeoPath($product);

        $this->assertSame('FINDOLOGIC-Product-EN/FINDOLOGIC001', $seoUrl);
    }
}
