<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Response;

use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\CategoryFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\ColorPickerFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Media;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\RangeSliderFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\RatingFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\SelectDropdownFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\CategoryFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\ColorFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\Values\ImageFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21\Filter\VendorImageFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21ResponseParser;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\CategoryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\DefaultInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\SearchTermQueryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\ShoppingGuideInfoMessage;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\VendorInfoMessage;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ExtensionHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\MockResponseHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class Xml21ResponseParserTest extends TestCase
{
    use KernelTestBehaviour;
    use MockResponseHelper;
    use ExtensionHelper;
    use ConfigHelper;

    /**
     * @var ServiceConfigResource|MockObject
     */
    private $serviceConfigResource;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ServiceConfigResource|MockObject $serviceConfigResource */
        $this->serviceConfigResource = $this->createMock(ServiceConfigResource::class);
    }

    public function productIdsResponseProvider(): array
    {
        return [
            'default mock ids' => [
                'response' => new Xml21Response($this->getMockResponse()),
                'expectedIds' => [
                    '019111105-37900' => '019111105-37900',
                    '029214085-37860' => '029214085-37860'
                ]
            ],
            'response without products' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithNoResults.xml')
                ),
                'expectedIds' => []
            ],
            'response with one product' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithOneProduct.xml')
                ),
                'expectedIds' => [
                    '029214085-37860' => '029214085-37860'
                ]
            ],
            'response with many products' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithManyProducts.xml')
                ),
                'expectedIds' => [
                    '102' => '102',
                    '103' => '103',
                    '104' => '104',
                    '105' => '105',
                    '106' => '106',
                    '107' => '107',
                    '108' => '108',
                    '109' => '109',
                    '110' => '110',
                    '111' => '111',
                    '112' => '112',
                    '113' => '113',
                    '114' => '114',
                    '115' => '115',
                    '116' => '116',
                    '117' => '117',
                    '118' => '118',
                    '119' => '119',
                    '120' => '120',
                    '121' => '121',
                    '122' => '122',
                    '123' => '123',
                    '124' => '124',
                    '125' => '125',
                    '126' => '126',
                    '127' => '127',
                    '128' => '128',
                    '129' => '129',
                    '130' => '130',
                    '131' => '131',
                    '132' => '132',
                    '133' => '133',
                    '134' => '134',
                    '135' => '135',
                ]
            ]
        ];
    }

    /**
     * @dataProvider productIdsResponseProvider
     */
    public function testProductIdsAreParsedAsExpected(Response $response, array $expectedIds): void
    {
        $responseParser = new Xml21ResponseParser($response);

        $this->assertEquals($expectedIds, $responseParser->getProductIds());
    }

    public function testSmartDidYouMeanExtensionIsReturned(): void
    {
        $response = new Xml21Response($this->getMockResponse());
        $responseParser = new Xml21ResponseParser($response);

        $request = new Request();
        $extension = $responseParser->getSmartDidYouMeanExtension($request);

        $this->assertEquals('did-you-mean', $extension->getType());
        $this->assertEquals('?search=ps4&forceOriginalQuery=1', $extension->getLink());
        $this->assertEquals('ps4', $extension->getAlternativeQuery());
        $this->assertEquals('', $extension->getOriginalQuery());
    }

    public function testLandingPageUriIsReturned(): void
    {
        $response = new Xml21Response($this->getMockResponse('XMLResponse/demoResponseWithLandingPage.xml'));
        $responseParser = new Xml21ResponseParser($response);

        $this->assertEquals('https://blubbergurken.io', $responseParser->getLandingPageExtension()->getLink());
    }

    public function testNoLandingPageIsReturnedIfResponseDoesNotHaveALandingPage(): void
    {
        $response = new Xml21Response($this->getMockResponse());
        $responseParser = new Xml21ResponseParser($response);

        $this->assertNull($responseParser->getLandingPageExtension());
    }

    public function testPromotionExtensionIsReturned(): void
    {
        $response = new Xml21Response($this->getMockResponse());
        $responseParser = new Xml21ResponseParser($response);
        $promotion = $responseParser->getPromotionExtension();

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertEquals('https://promotion.com/', $promotion->getLink());
        $this->assertEquals('https://promotion.com/promotion.png', $promotion->getImage());
    }

    public function filterResponseProvider(): array
    {
        $expectedCategoryFilter = new CategoryFilter('cat', 'Kategorie');
        $expectedCategoryFilter->addValue(
            (new CategoryFilterValue('Buch', 'Buch'))
                ->setFrequency(5)
                ->addValue(
                    (new CategoryFilterValue('Beste Bücher', 'Beste Bücher'))
                )
        );

        $vendor = 'vendor';
        $expectedVendorFilter = new VendorImageFilter($vendor, 'Hersteller');
        $expectedVendorFilter->addValue(
            (new ImageFilterValue('Anderson, Gusikowski and Barton', 'Anderson, Gusikowski and Barton', $vendor))
                ->setDisplayType('media')
                ->setMedia(new Media('https://demo.findologic.com/vendor/anderson_gusikowski_and_barton.png'))
        );
        $expectedVendorFilter->addValue(
            (new ImageFilterValue('Bednar Ltd', 'Bednar Ltd', $vendor))
                ->setDisplayType('media')
                ->setMedia(new Media('https://demo.findologic.com/vendor/bednar_ltd.png'))
        );
        $expectedVendorFilter->addValue(
            (new ImageFilterValue('Buckridge-Fisher', 'Buckridge-Fisher', $vendor))
                ->setDisplayType('media')
                ->setMedia(new Media('https://demo.findologic.com/vendor/buckridge_fisher.png'))
        );
        $expectedVendorFilter->addValue(
            (new ImageFilterValue('Connelly, Eichmann and Weissnat', 'Connelly, Eichmann and Weissnat', $vendor))
                ->setDisplayType('media')
                ->setMedia(new Media('https://demo.findologic.com/vendor/connelly_eichmann_and_weissnat.png'))
        );

        $price = 'price';
        $expectedPriceFilter = new RangeSliderFilter($price, 'Preis');
        $expectedPriceFilter->addValue(new FilterValue('0.39 - 13.4', '0.39 - 13.4', $price));
        $expectedPriceFilter->addValue(new FilterValue('13.45 - 25.99', '13.45 - 25.99', $price));
        $expectedPriceFilter->addValue(new FilterValue('26 - 40.3', '26 - 40.3', $price));
        $expectedPriceFilter->setMin(0.39);
        $expectedPriceFilter->setMax(2239.1);
        $expectedPriceFilter->setStep(0.1);

        $expectedRatingFilter = new RatingFilter('rating', 'Rating');
        $expectedRatingFilter->setMaxPoints(5.0);
        $expectedRatingFilter->addValue(new FilterValue('0.0', '0.0'));
        $expectedRatingFilter->addValue(new FilterValue('5.0', '5.0'));

        $color = 'Farbe';
        $expectedColorFilter = new ColorPickerFilter($color, 'Farbe');
        $expectedColorFilter->addValue(
            (new ColorFilterValue('beige', 'beige', $color))
                ->setColorHexCode('#F5F5DC')
                ->setMedia(new Media('https://blubbergurken.io/farbfilter/beige.gif'))
                ->setDisplayType('media')
        );
        $expectedColorFilter->addValue(
            (new ColorFilterValue('blau', 'blau', $color))
                ->setColorHexCode('#3c6380')
                ->setMedia(new Media('https://blubbergurken.io/farbfilter/blau.gif'))
                ->setDisplayType('media')
        );
        $expectedColorFilter->addValue(
            (new ColorFilterValue('braun', 'braun', $color))
                ->setColorHexCode('#94651e')
                ->setMedia(new Media('https://blubbergurken.io/farbfilter/braun.gif'))
                ->setDisplayType('media')
        );

        $material = 'Material';
        $expectedSelectDropdownFilter = new SelectDropdownFilter($material, 'Material');
        $expectedSelectDropdownFilter->addValue(new FilterValue('Hartgepäck', 'Hartgepäck', $material));
        $expectedSelectDropdownFilter->addValue(new FilterValue('Leder', 'Leder', $material));
        $expectedSelectDropdownFilter->addValue(new FilterValue('Nylon', 'Nylon', $material));

        return [
            'response including all filter types' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithAllFilterTypes.xml')
                ),
                'expectedFilters' => [
                    'cat' => $expectedCategoryFilter,
                    'vendor' => $expectedVendorFilter,
                    'price' => $expectedPriceFilter,
                    'Farbe' => $expectedColorFilter,
                    'Material' => $expectedSelectDropdownFilter,
                    'rating' => $expectedRatingFilter
                ]
            ],
            'response without results' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithNoResults.xml')
                ),
                'expectedFilters' => []
            ],
            'response without results but with filters with no-filters-available-text' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithNoResultsButWithFilters.xml')
                ),
                'expectedFilters' => []
            ],
            'response with colors without image URLs' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithColorFiltersWithoutUrl.xml')
                ),
                'expectedFilters' => [
                    'Farbe' => (new ColorPickerFilter('Farbe', 'Farbe'))
                        ->addValue(
                            (new ColorFilterValue('beige', 'beige', $color))
                                ->setMedia(new Media(''))
                                ->setColorHexCode('#F5F5DC')
                                ->setDisplayType('color')
                        )
                        ->addValue(
                            (new ColorFilterValue('blau', 'blau', $color))
                                ->setMedia(new Media(''))
                                ->setColorHexCode('#3c6380')
                                ->setDisplayType('color')
                        )
                        ->addValue(
                            (new ColorFilterValue('braun', 'braun', $color))
                                ->setMedia(new Media(''))
                                ->setColorHexCode('')
                                ->setDisplayType('none')
                        )
                ]
            ]
        ];
    }

    /**
     * @dataProvider filterResponseProvider
     */
    public function testFiltersAreReturnedAsExpected(Xml21Response $response, array $expectedFilters): void
    {
        $responseParser = new Xml21ResponseParser($response);

        $filtersExtension = $responseParser->getFiltersExtension();
        $filters = $filtersExtension->getFilters();

        $this->assertEquals($expectedFilters, $filters);
    }

    public function smartSuggestBlocksProvider()
    {
        return [
            'No smart suggest blocks are sent and category filter is not in response' => [
                'type' => 'cat',
                'demoResponse' => 'demoResponseWithoutCategoryFilter.xml',
                'flBlocks' => [],
                'expectedFilterName' => null,
                'expectedInstanceOf' => CategoryFilter::class,
                'isHidden' => null
            ],
            'Smart suggest blocks are sent and category filter is not in response' => [
                'type' => 'cat',
                'demoResponse' => 'demoResponseWithoutCategoryFilter.xml',
                'flBlocks' => ['cat' => 'Category'],
                'expectedFilterName' => 'Category',
                'expectedInstanceOf' => CategoryFilter::class,
                'isHidden' => true
            ],
            'No smart suggest blocks are sent and category filter is available in response' => [
                'type' => 'cat',
                'demoResponse' => 'demoResponseWithCategoryFilter.xml',
                'flBlocks' => [],
                'expectedFilterName' => 'Kategorie',
                'expectedInstanceOf' => CategoryFilter::class,
                'isHidden' => false
            ],
            'No smart suggest blocks are sent and vendor filter is not in response' => [
                'type' => 'vendor',
                'demoResponse' => 'demoResponseWithoutVendorFilter.xml',
                'flBlocks' => [],
                'expectedFilterName' => null,
                'expectedInstanceOf' => VendorImageFilter::class,
                'isHidden' => null
            ],
            'Smart suggest blocks are sent and vendor filter is not in response' => [
                'type' => 'vendor',
                'demoResponse' => 'demoResponseWithoutVendorFilter.xml',
                'flBlocks' => ['vendor' => 'Manufacturer'],
                'expectedFilterName' => 'Manufacturer',
                'expectedInstanceOf' => VendorImageFilter::class,
                'isHidden' => true
            ],
            'No smart suggest blocks are sent and vendor filter is available in response' => [
                'type' => 'vendor',
                'demoResponse' => 'demoResponseWithVendorFilter.xml',
                'flBlocks' => [],
                'expectedFilterName' => 'Hersteller',
                'expectedInstanceOf' => VendorImageFilter::class,
                'isHidden' => false
            ],
        ];
    }

    /**
     * @dataProvider smartSuggestBlocksProvider
     */
    public function testHiddenFiltersBasedOnSmartSuggestBlocks(
        string $type,
        string $demoResponse,
        array $smartSuggestBlocks,
        ?string $expectedFilterName,
        ?string $expectedInstanceOf,
        ?bool $isHidden
    ): void {
        $response = new Xml21Response(
            $this->getMockResponse(sprintf('XMLResponse/%s', $demoResponse))
        );

        $responseParser = new Xml21ResponseParser($response);

        $filtersExtension = $responseParser->getFiltersExtension();
        $filtersExtension = $responseParser->getFiltersWithSmartSuggestBlocks(
            $filtersExtension,
            $smartSuggestBlocks,
            [$type => 'Some Value']
        );

        $filters = $filtersExtension->getFilters();
        $filter = end($filters);
        if ($expectedFilterName === null) {
            $this->assertNotInstanceOf($expectedInstanceOf, $filter);
        } else {
            $this->assertInstanceOf($expectedInstanceOf, $filter);
            $this->assertSame($expectedFilterName, $filter->getName());
            $this->assertSame($isHidden, $filter->isHidden());
        }
    }

    public function paginationResponseProvider(): array
    {
        return [
            'first page pagination with default values' => [
                'response' => new Xml21Response($this->getMockResponse()),
                'limit' => null,
                'offset' => null,
                'expectedTotal' => 1808,
                'expectedOffset' => 0,
                'expectedLimit' => 24
            ],
            'second page with override of user' => [
                'response' => new Xml21Response($this->getMockResponse()),
                'limit' => 24,
                'offset' => 24,
                'expectedTotal' => 1808,
                'expectedOffset' => 24,
                'expectedLimit' => 24
            ],
            'third page with different limit' => [
                'response' => new Xml21Response($this->getMockResponse()),
                'limit' => 100,
                'offset' => 200,
                'expectedTotal' => 1808,
                'expectedOffset' => 200,
                'expectedLimit' => 100
            ],
        ];
    }

    /**
     * @dataProvider paginationResponseProvider
     */
    public function testPaginationExtensionIsReturnedAsExpected(
        Xml21Response $response,
        ?int $limit,
        ?int $offset,
        int $expectedTotal,
        int $expectedOffset,
        int $expectedLimit
    ): void {
        $responseParser = new Xml21ResponseParser($response);

        $pagination = $responseParser->getPaginationExtension($limit, $offset);

        $this->assertEquals($expectedTotal, $pagination->getTotal());
        $this->assertEquals($expectedOffset, $pagination->getOffset());
        $this->assertEquals($expectedLimit, $pagination->getLimit());
    }

    public function queryInfoMessageResponseProvider(): array
    {
        return [
            'alternative query is used' => [
                'response' => new Xml21Response($this->getMockResponse()),
                'request' => new Request(),
                'expectedInstance' => SearchTermQueryInfoMessage::class,
                'expectedVars' => [
                    'query' => 'ps4',
                    'extensions' => []
                ]
            ],
            'no alternative query - search query is used' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithoutAlternativeQuery.xml')
                ),
                'request' => new Request(),
                'expectedInstance' => SearchTermQueryInfoMessage::class,
                'expectedVars' => [
                    'query' => 'ps3',
                    'extensions' => []
                ]
            ],
            'no search query but selected category' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithoutQuery.xml')
                ),
                'request' => new Request(['cat' => 'Shoes & More']),
                'expectedInstance' => CategoryInfoMessage::class,
                'expectedVars' => [
                    'filterName' => 'Kategorie',
                    'filterValue' => 'Shoes & More',
                    'extensions' => []
                ]
            ],
            'no search query but selected vendor' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithoutQuery.xml')
                ),
                'request' => new Request(['vendor' => 'Blubbergurken inc.']),
                'expectedInstance' => VendorInfoMessage::class,
                'expectedVars' => [
                    'filterName' => 'Hersteller',
                    'filterValue' => 'Blubbergurken inc.',
                    'extensions' => []
                ]
            ],
            'no query and no selected filters' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithoutQuery.xml')
                ),
                'request' => new Request(),
                'expectedInstance' => DefaultInfoMessage::class,
                'expectedVars' => [
                    'extensions' => []
                ]
            ],
            'shopping guide query is used' => [
                'response' => new Xml21Response(
                    $this->getMockResponse('XMLResponse/demoResponseWithoutQuery.xml')
                ),
                'request' => new Request(['wizard' => 'FindologicGuide']),
                'expectedInstance' => ShoppingGuideInfoMessage::class,
                'expectedVars' => [
                    'shoppingGuide' => 'FindologicGuide',
                    'extensions' => []
                ]
            ],
        ];
    }

    /**
     * @dataProvider queryInfoMessageResponseProvider
     */
    public function testQueryInfoMessageExtensionIsReturnedAsExpected(
        Xml21Response $response,
        Request $request,
        string $expectedInstance,
        array $expectedVars
    ): void {
        $responseParser = new Xml21ResponseParser($response);

        $contextMock = $this->getMockBuilder(Context::class)
            ->onlyMethods(['getExtension'])
            ->disableOriginalConstructor()
            ->getMock();

        $contextMock->expects($this->any())
            ->method('getExtension')
            ->willReturn($this->getDefaultSmartDidYouMeanExtension());

        /** @var SalesChannelContext|MockObject $salesChannelContextMock */
        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)
            ->onlyMethods(['getContext'])
            ->disableOriginalConstructor()
            ->getMock();

        $salesChannelContextMock->expects($this->any())->method('getContext')->willReturn($contextMock);
        $event = new ProductListingCriteriaEvent($request, new Criteria(), $salesChannelContextMock);

        $queryInfoMessage = $responseParser->getQueryInfoMessage($event);
        $this->assertInstanceOf($expectedInstance, $queryInfoMessage);
        $this->assertEquals($expectedVars, $queryInfoMessage->getVars());
    }

    public function testRatingFilterIsNotShownIfMinAndMaxAreTheSame(): void
    {
        $response = new Xml21Response(
            $this->getMockResponse('XMLResponse/demoResponseWithRatingFilterMinMaxAreSame.xml')
        );
        $responseParser = new Xml21ResponseParser($response);
        $filtersExtension = $responseParser->getFiltersExtension();

        $this->assertEmpty($filtersExtension->getFilters());
    }
}
