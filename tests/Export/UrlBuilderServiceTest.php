<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\CatUrlBuilderService;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use FINDOLOGIC\FinSearch\Tests\TestHelper\Helper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Symfony\Component\Routing\RouterInterface;

class UrlBuilderServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductHelper;
    use SalesChannelHelper;

    private SalesChannelContext $salesChannelContext;

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

        $this->urlBuilderService = new CatUrlBuilderService($routerMock, $categoryRepository);
        $this->urlBuilderService->setSalesChannelContext($this->salesChannelContext);
    }

    public function removeInvalidUrlsProvider(): array
    {
        return [
            'All valid urls' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => '/correctSeoUrl-One' ],
                    [ 'seoPathInfo' => '/correctSeoUrlTwo' ],
                    [ 'seoPathInfo' => '/correctSeoUrl/Three' ]
                ],
                'expectedUrlCount' => 3
            ],
            'Half valid urls' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => '/failed seo url with spaces' ],
                    [ 'seoPathInfo' => 'failedSeoUrlWithoutSlash' ],
                    [ 'seoPathInfo' => '/correctSeoUrl-One' ],
                    [ 'seoPathInfo' => '/correctSeoUrlTwo' ]
                ],
                'expectedUrlCount' => 2
            ],
            'All Invalid urls' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => '/failed seo url with spaces' ],
                    [ 'seoPathInfo' => 'failedSeoUrlWithoutSlash' ],
                    [ 'seoPathInfo' => 'failedSeoUrlWithoutSlash and with spaces' ],
                ],
                'expectedUrlCount' => 0
            ],
        ];
    }

    /**
     * @dataProvider removeInvalidUrlsProvider
     */
    public function testRemoveInvalidUrls(array $seoUrlArray, int $expectedUrlCount): void
    {
        $seoUrlCollection = new SeoUrlCollection();
        foreach ($seoUrlArray as $seoUrl) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setId(Uuid::randomHex());
            $seoUrlEntity->setSeoPathInfo($seoUrl['seoPathInfo']);

            $seoUrlCollection->add($seoUrlEntity);
        }

        $this->assertSame(
            $expectedUrlCount,
            Helper::callMethod(
                $this->urlBuilderService,
                'removeInvalidUrls',
                [$seoUrlCollection]
            )->count()
        );
    }

    public function productSeoPathProvider(): array
    {
        return [
            'Has valid url, canonical and not deleted' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => 'invalid url one', 'isCanonical' => false, 'isDeleted' => false ],
                    [ 'seoPathInfo' => '/validUrlOne', 'isCanonical' => true, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => 'validUrlOne'
            ],
            'Has valid url not canonical and not deleted' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => 'invalid url two', 'isCanonical' => false, 'isDeleted' => false ],
                    [ 'seoPathInfo' => '/validUrlTwo', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => 'validUrlTwo'
            ],
            'Has valid and canonical url, but deleted' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => 'invalid url five', 'isCanonical' => false, 'isDeleted' => false ],
                    [ 'seoPathInfo' => '/validUrlThree', 'isCanonical' => true, 'isDeleted' => true ]
                ],
                'expectedSeoUrl' => null
            ],
            'Has valid and not canonical url and deleted' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => 'invalid url five', 'isCanonical' => false, 'isDeleted' => false ],
                    [ 'seoPathInfo' => '/validUrlFour', 'isCanonical' => false, 'isDeleted' => true ]
                ],
                'expectedSeoUrl' => null
            ],
            'No valid url, all not canonical' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => 'invalid url three', 'isCanonical' => false, 'isDeleted' => false ],
                    [ 'seoPathInfo' => 'invalid url four', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => null
            ]
        ];
    }

    /**
     * @dataProvider productSeoPathProvider
     */
    public function testGetProductSeoPath(array $seoUrlArray, ?string $expectedSeoUrl): void
    {
        $seoUrlCollection = new SeoUrlCollection();
        $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();
        $languageId = $this->salesChannelContext->getSalesChannel()->getLanguageId();

        foreach ($seoUrlArray as $seoPath) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setId(Uuid::randomHex());
            $seoUrlEntity->setSalesChannelId($salesChannelId);
            $seoUrlEntity->setLanguageId($languageId);
            $seoUrlEntity->setSeoPathInfo($seoPath['seoPathInfo']);
            $seoUrlEntity->setIsCanonical($seoPath['isCanonical']);
            $seoUrlEntity->setIsDeleted($seoPath['isDeleted']);

            $seoUrlCollection->add($seoUrlEntity);
        }

        $product = $this->createTestProduct();
        $product->setSeoUrls($seoUrlCollection);

        $seoUrl = Helper::callMethod(
            $this->urlBuilderService,
            'getProductSeoPath',
            [$product]
        );

        $this->assertSame($expectedSeoUrl, $seoUrl);
    }
}
