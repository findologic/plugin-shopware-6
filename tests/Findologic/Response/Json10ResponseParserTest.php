<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Response;

use FINDOLOGIC\Api\Responses\Json10\Json10Response;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\CategoryFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\ColorPickerFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\LabelTextFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Media;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\RangeSliderFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\RatingFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\SelectDropdownFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\CategoryFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\ColorFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\FilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\Values\ImageFilterValue;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10\Filter\VendorImageFilter;
use FINDOLOGIC\FinSearch\Findologic\Response\Json10ResponseParser;
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

class Json10ResponseParserTest extends TestCase
{
    use KernelTestBehaviour;
    use MockResponseHelper;
    use ExtensionHelper;
    use ConfigHelper;

    /** @var ServiceConfigResource|MockObject */
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
                'response' => new Json10Response($this->getMockResponse()),
                'expectedIds' => [
                    '019111105-37900',
                    '029214085-37860'
                ]
            ],
            'response without products' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithNoResults.json')
                ),
                'expectedIds' => []
            ],
            'response with one product' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithOneProduct.json')
                ),
                'expectedIds' => [
                    '029214085-37860'
                ]
            ],
            'response with many products' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithManyProducts.json')
                ),
                'expectedIds' => [
                    '102',
                    '103',
                    '104',
                    '105',
                    '106',
                    '107',
                    '108',
                    '109',
                    '110',
                    '111',
                    '112',
                    '113',
                    '114',
                    '115',
                    '116',
                    '117',
                    '118',
                    '119',
                    '120',
                    '121',
                    '122',
                    '123',
                    '124',
                    '125',
                    '126',
                    '127',
                    '128',
                    '129',
                    '130',
                    '131',
                    '132',
                    '133',
                    '134',
                    '135',
                ]
            ]
        ];
    }

    /**
     * @dataProvider productIdsResponseProvider
     */
    public function testProductIdsAreParsedAsExpected(Response $response, array $expectedIds): void
    {
        $responseParser = new Json10ResponseParser($response);

        $this->assertEquals($expectedIds, $responseParser->getProductIds());
    }

    public function testSmartDidYouMeanExtensionIsReturned(): void
    {
        $response = new Json10Response($this->getMockResponse('JSONResponse/demoResponseWithDidYouMeanQuery.json'));
        $responseParser = new Json10ResponseParser($response);

        $request = new Request();
        $extension = $responseParser->getSmartDidYouMeanExtension($request);

        $this->assertEquals('did-you-mean', $extension->getType());
        $this->assertEquals('?search=didYouMean&forceOriginalQuery=1', $extension->getLink());
        $this->assertEquals('didYouMean', $extension->getDidYouMeanQuery());
        $this->assertEquals('query', $extension->getOriginalQuery());
    }

    public function testLandingPageUriIsReturned(): void
    {
        $response = new Json10Response($this->getMockResponse('JSONResponse/demoResponseWithLandingPage.json'));
        $responseParser = new Json10ResponseParser($response);

        $this->assertEquals('https://blubbergurken.io', $responseParser->getLandingPageExtension()->getLink());
    }

    public function testNoLandingPageIsReturnedIfResponseDoesNotHaveALandingPage(): void
    {
        $response = new Json10Response($this->getMockResponse());
        $responseParser = new Json10ResponseParser($response);

        $this->assertNull($responseParser->getLandingPageExtension());
    }

    public function testPromotionExtensionIsReturned(): void
    {
        $response = new Json10Response($this->getMockResponse());
        $responseParser = new Json10ResponseParser($response);
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
        $expectedPriceFilter->addValue(new FilterValue('0.39 - 13.40', '0.39 - 13.40', $price));
        $expectedPriceFilter->addValue(new FilterValue('13.45 - 25.99', '13.45 - 25.99', $price));
        $expectedPriceFilter->addValue(new FilterValue('26.00 - 40.30', '26.00 - 40.30', $price));
        $expectedPriceFilter->setMin(0.355);
        $expectedPriceFilter->setMax(3239.1455);
        $expectedPriceFilter->setStep(0.1);
        $expectedPriceFilter->setUnit('€');
        $expectedPriceFilter->setTotalRange([
            'min' => 0.355,
            'max' => 3239.1455
        ]);
        $expectedPriceFilter->setSelectedRange([
            'min' => 0.395,
            'max' => 2239.144
        ]);

        $expectedRatingFilter = new RatingFilter('rating', 'Rating');
        $expectedRatingFilter->setMaxPoints(5.0);
        $expectedRatingFilter->addValue(new FilterValue('0.00 - 0.00', '0.00 - 0.00'));
        $expectedRatingFilter->addValue(new FilterValue('0.00 - 0.00', '0.00 - 0.00'));

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
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithAllFilterTypes.json')
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
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithNoResults.json')
                ),
                'expectedFilters' => []
            ],
            'response without results but with filters with no-filters-available-text' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithNoResultsButWithFilters.json')
                ),
                'expectedFilters' => []
            ],
            'response with colors without image URLs' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithColorFiltersWithoutUrl.json')
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
    public function testFiltersAreReturnedAsExpected(Json10Response $response, array $expectedFilters): void
    {
        $responseParser = new Json10ResponseParser($response);

        $filtersExtension = $responseParser->getFiltersExtension();
        $filters = $filtersExtension->getFilters();

        $this->assertEquals($expectedFilters, $filters);
    }

    public static function smartSuggestBlocksProvider()
    {
        return [
            'No smart suggest blocks are sent and category filter is not in response' => [
                'type' => 'cat',
                'demoResponse' => 'demoResponseWithoutFilters.json',
                'flBlocks' => [],
                'expectedFilterName' => null,
                'expectedInstanceOf' => CategoryFilter::class,
                'isHidden' => null
            ],
            'Smart suggest blocks are sent and category filter is not in response' => [
                'type' => 'cat',
                'demoResponse' => 'demoResponseWithoutFilters.json',
                'flBlocks' => ['cat' => 'Category'],
                'expectedFilterName' => 'Category',
                'expectedInstanceOf' => CategoryFilter::class,
                'isHidden' => true
            ],
            'No smart suggest blocks are sent and category filter is available in response' => [
                'type' => 'cat',
                'demoResponse' => 'demoResponseWithCategoryFilter.json',
                'flBlocks' => [],
                'expectedFilterName' => 'Kategorie',
                'expectedInstanceOf' => CategoryFilter::class,
                'isHidden' => false
            ],
            'No smart suggest blocks are sent and vendor filter is not in response' => [
                'type' => 'vendor',
                'demoResponse' => 'demoResponseWithoutFilters.json',
                'flBlocks' => [],
                'expectedFilterName' => null,
                'expectedInstanceOf' => VendorImageFilter::class,
                'isHidden' => null
            ],
            'Smart suggest blocks are sent and vendor filter is not in response' => [
                'type' => 'vendor',
                'demoResponse' => 'demoResponseWithoutFilters.json',
                'flBlocks' => ['vendor' => 'Manufacturer'],
                'expectedFilterName' => 'Manufacturer',
                'expectedInstanceOf' => VendorImageFilter::class,
                'isHidden' => true
            ],
            'No smart suggest blocks are sent and vendor filter is available in response' => [
                'type' => 'vendor',
                'demoResponse' => 'demoResponseWithVendorFilter.json',
                'flBlocks' => [],
                'expectedFilterName' => 'Hersteller',
                'expectedInstanceOf' => VendorImageFilter::class,
                'isHidden' => false
            ],
            'No smart suggest blocks are sent and text vendor filter is available in response' => [
                'type' => 'vendor',
                'demoResponse' => 'demoResponseWithTextVendorFilter.json',
                'flBlocks' => [],
                'expectedFilterName' => 'Hersteller',
                'expectedInstanceOf' => LabelTextFilter::class,
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
        $response = new Json10Response(
            $this->getMockResponse(sprintf('JSONResponse/%s', $demoResponse))
        );

        $responseParser = new Json10ResponseParser($response);

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
                'response' => new Json10Response($this->getMockResponse()),
                'limit' => null,
                'offset' => null,
                'expectedTotal' => 1808,
                'expectedOffset' => 0,
                'expectedLimit' => 24
            ],
            'second page with override of user' => [
                'response' => new Json10Response($this->getMockResponse()),
                'limit' => 24,
                'offset' => 24,
                'expectedTotal' => 1808,
                'expectedOffset' => 24,
                'expectedLimit' => 24
            ],
            'third page with different limit' => [
                'response' => new Json10Response($this->getMockResponse()),
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
        Json10Response $response,
        ?int $limit,
        ?int $offset,
        int $expectedTotal,
        int $expectedOffset,
        int $expectedLimit
    ): void {
        $responseParser = new Json10ResponseParser($response);

        $pagination = $responseParser->getPaginationExtension($limit, $offset);

        $this->assertEquals($expectedTotal, $pagination->getTotal());
        $this->assertEquals($expectedOffset, $pagination->getOffset());
        $this->assertEquals($expectedLimit, $pagination->getLimit());
    }

    public function queryInfoMessageResponseProvider(): array
    {
        return [
            'alternative query is used' => [
                'response' => new Json10Response($this->getMockResponse()),
                'request' => new Request(),
                'expectedInstance' => SearchTermQueryInfoMessage::class,
                'expectedVars' => [
                    'query' => 'ps4',
                    'extensions' => []
                ]
            ],
            'no search query but selected category' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithoutQuery.json')
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
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithoutQuery.json')
                ),
                'request' => new Request(['vendor' => 'vendor>Blubbergurken inc.']),
                'expectedInstance' => VendorInfoMessage::class,
                'expectedVars' => [
                    'filterName' => 'Hersteller',
                    'filterValue' => 'Blubbergurken inc.',
                    'extensions' => []
                ]
            ],
            'no search query but 2 selected vendors' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithoutQuery.json')
                ),
                'request' => new Request(['vendor' => 'vendor>Blubbergurken inc.|vendor>Blubbergurken Limited']),
                'expectedInstance' => DefaultInfoMessage::class,
                'expectedVars' => [
                    'extensions' => []
                ]
            ],
            'no query and no selected filters' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithoutQuery.json')
                ),
                'request' => new Request(),
                'expectedInstance' => DefaultInfoMessage::class,
                'expectedVars' => [
                    'extensions' => []
                ]
            ],
            'shopping guide query is used' => [
                'response' => new Json10Response(
                    $this->getMockResponse('JSONResponse/demoResponseWithoutQuery.json')
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
        Json10Response $response,
        Request $request,
        string $expectedInstance,
        array $expectedVars
    ): void {
        $responseParser = new Json10ResponseParser($response);

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
        $response = new Json10Response(
            $this->getMockResponse('JSONResponse/demoResponseWithRatingFilterMinMaxAreSame.json')
        );
        $responseParser = new Json10ResponseParser($response);
        $filtersExtension = $responseParser->getFiltersExtension();

        $this->assertEmpty($filtersExtension->getFilters());
    }
}
