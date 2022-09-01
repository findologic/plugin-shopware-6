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
            'Has valid url, all canonical and not deleted' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => '/validUrlOne', 'isCanonical' => true, 'isDeleted' => false ],
                    [ 'seoPathInfo' => 'invalid url one', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => 'validUrlOne'
            ],
            'Has valid url not canonical and not deleted' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => '/validUrlTwo', 'isCanonical' => true, 'isDeleted' => false ],
                    [ 'seoPathInfo' => 'invalid url two', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => 'validUrlTwo'
            ],
            'No valid url, all not canonical' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => 'invalid url three', 'isCanonical' => false, 'isDeleted' => false ],
                    [ 'seoPathInfo' => 'invalid url four', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => null
            ],
            'Has valid and canonical url, but deleted' => [
                'seoUrlArray' => [
                    [ 'seoPathInfo' => '/validUrlThree', 'isCanonical' => true, 'isDeleted' => true ],
                    [ 'seoPathInfo' => 'invalid url five', 'isCanonical' => false, 'isDeleted' => false ]
                ],
                'expectedSeoUrl' => null
            ]
        ];
    }
}
