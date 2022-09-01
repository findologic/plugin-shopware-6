<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\MockObject\MockObject;
use FINDOLOGIC\FinSearch\Tests\Utils\UtilsTest;
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
        $expectedUrlCount = 2;
        $seoPathInfos = [
            '/failed seo url with spaces',
            'failedSeoUrlWithoutSlash',
            '/correctSeoUrl-One',
            '/correctSeoUrlTwo'
        ];

        $seoUrlCollection = new SeoUrlCollection();
        foreach ($seoPathInfos as $seoPathInfo) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setId(Uuid::randomHex());
            $seoUrlEntity->setSeoPathInfo($seoPathInfo);

            $seoUrlCollection->add($seoUrlEntity);
        }

        $this->assertSame(
            $expectedUrlCount,
            UtilsTest::callMethod(
                $this->urlBuilderService,
                'removeInvalidUrls',
                [$seoUrlCollection]
            )->count()
        );
    }

    /**
     * @dataProvider productSeoPathProvider
     */
    public function testGetProductSeoPath(array $seoPathCollectionArray, ?string $expectedSeoUrl): void
    {
        $product = $this->createTestProduct();
        $languageId = $this->salesChannelContext->getSalesChannel()->getLanguageId();
        $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();
        $seoUrlCollection = new SeoUrlCollection();

        foreach ($seoPathCollectionArray as $seoPath) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setId(Uuid::randomHex());
            $seoUrlEntity->setIsCanonical($seoPath['isCanonical']);
            $seoUrlEntity->setSalesChannelId($salesChannelId);
            $seoUrlEntity->setLanguageId($languageId);
            $seoUrlEntity->setIsDeleted($seoPath['isDeleted']);
            $seoUrlEntity->setSeoPathInfo($seoPath['seoPathInfo']);

            $seoUrlCollection->add($seoUrlEntity);
        }

        $product->setSeoUrls($seoUrlCollection);

        $seoUrl = UtilsTest::callMethod(
            $this->urlBuilderService,
            'getProductSeoPath',
            [$product]
        );

        $this->assertSame($expectedSeoUrl, $seoUrl);
    }

    public function productSeoPathProvider(): array
    {
        return [
            'Has valid url, all canonical' => [
                'seoPathCollectionArray' => [
                    [ 'seoPathInfo' => '/validUrlOne', 'isCanonical' => true, 'isDeleted' => false ],
                    [ 'seoPathInfo' => 'invalid url one', 'isCanonical' => true, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => 'validUrlOne'
            ],
            'Has valid url, all not canonical' => [
                'seoPathCollectionArray' => [
                    [ 'seoPathInfo' => '/validUrlOne', 'isCanonical' => false, 'isDeleted' => false ],
                    [ 'seoPathInfo' => 'invalid url one', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => 'validUrlOne'
            ],
            'No valid url, all not canonical' => [
                'seoPathCollectionArray' => [
                    [ 'seoPathInfo' => 'invalid url one', 'isCanonical' => false, 'isDeleted' => false ],
                    [ 'seoPathInfo' => 'invalid url two', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => null
            ],
            'Valid url deleted' => [
                'seoPathCollectionArray' => [
                    [ 'seoPathInfo' => '/validUrlOne', 'isCanonical' => false, 'isDeleted' => true ],
                    [ 'seoPathInfo' => 'invalid url one', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => null
            ]
        ];
    }
}
