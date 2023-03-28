<?php

declare(strict_types=1);

namespace FinSearch\Tests\Findologic\Api;

use FINDOLOGIC\FinSearch\Findologic\Api\SortingService;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SortingServiceTest extends TestCase
{
    use SalesChannelHelper;
    use IntegrationTestBehaviour;

    public function availableSortingOptionsProvider(): array
    {
        $topsellerSorting = new ProductSortingEntity();
        $topsellerSorting->setId(Uuid::randomHex());
        $topsellerSorting->setActive(true);
        $topsellerSorting->setTranslated([
            'label' => $this->getContainer()->get('translator')->trans('filter.sortByScore')
        ]);
        $topsellerSorting->setKey('product.sales');
        $topsellerSorting->setPriority(5);
        $topsellerSorting->setFields([
            [
                'field' => 'product.sales',
                'order' => 'desc',
                'priority' => 1,
                'naturalSorting' => 0,
            ],
        ]);

        return [
            'no sorting options' => [
                'sortings' => new ProductSortingCollection(),
                'expectedSortings' => [$topsellerSorting]
            ],
            'sorting options containing topseller' => [
                'sortings' => new ProductSortingCollection([$topsellerSorting]),
                'expectedSortings' => [$topsellerSorting]
            ]
        ];
    }

    /**
     * @dataProvider availableSortingOptionsProvider
     */
    public function testTopSellerSortingIsOnlyAddedOnce(
        ProductSortingCollection $sortings,
        array $expectedSortings
    ): void {
        $sortingService = $this->buildSortingService();
        $event = new ProductListingCriteriaEvent(
            new Request(),
            new Criteria(),
            $this->buildAndCreateSalesChannelContext()
        );
        $searchRequestHandlerMock = $this->getMockBuilder(NavigationRequestHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event->getCriteria()->addExtension('sortings', $sortings);
        $sortingService->handleRequest($event, $searchRequestHandlerMock);

        $actualSortings = $event->getCriteria()->getExtension('sortings')->getElements();
        $this->assertCount(count($expectedSortings), $actualSortings);
    }

    private function buildSortingService(
        TranslatorInterface $translator = null
    ): SortingService {
        return new SortingService(
            $translator ?? $this->getContainer()->get('translator')
        );
    }
}
