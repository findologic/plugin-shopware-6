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
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
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
        $seoPathInfos = [
            '/failed seo url with spaces',
            'failedSeoUrlWithoutSlash',
            '/correctSeoUrl-One',
            '/correctSeoUrlTwo'
        ];
        $seoUrlCollection = [];
        foreach ($seoPathInfos as $seoPathInfo) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setSeoPathInfo($seoPathInfo);
            $seoUrlEntity->setId($seoPathInfo);
            array_push($seoUrlCollection, $seoUrlEntity);
        }
        $allowedUrls = $this->urlBuilderService->removeInvalidUrls($seoUrlCollection);

        $this->assertSame(2, $allowedUrls->count());
    }
}
