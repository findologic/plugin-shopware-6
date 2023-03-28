<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Core\Content\Product\SalesChannel\Listing\SortingHandler;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\SortingHandler\ReleaseDateSortingHandler;
use FINDOLOGIC\FinSearch\Tests\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ReleaseDateSortingHandlerTest extends TestCase
{
    public function testSupportsReleaseDateSorting(): void
    {
        $fieldSorting = new FieldSorting('product.releaseDate');
        $releaseDateSortingHandler = new ReleaseDateSortingHandler();

        $this->assertTrue($releaseDateSortingHandler->supportsSorting($fieldSorting));
    }

    public static function nonSupportedSortingsProvider(): array
    {
        return [
            'price sorting' => [
                'sorting' => new FieldSorting('product.listingPrices')
            ],
            'product name sorting' => [
                'sorting' => new FieldSorting('product.name')
            ],
            'relevance score sorting' => [
                'sorting' => new FieldSorting('_score')
            ],
            'topseller sorting' => [
                'sorting' => new FieldSorting('product.sales')
            ]
        ];
    }

    /**
     * @dataProvider nonSupportedSortingsProvider
     */
    public function testNotSupportsNonReleaseDateSorting(FieldSorting $sorting): void
    {
        $releaseDateSortingHandler = new ReleaseDateSortingHandler();

        $this->assertFalse($releaseDateSortingHandler->supportsSorting($sorting));
    }

    public function testSortingIsSentToApi(): void
    {
        $fieldSorting = new FieldSorting('product.releaseDate', FieldSorting::DESCENDING);
        $request = new SearchRequest();
        $releaseDateSortingHandler = new ReleaseDateSortingHandler();

        $releaseDateSortingHandler->generateSorting($fieldSorting, $request);

        $this->assertSame('dateadded DESC', $request->getParams()['order']);
    }
}
