<?php

declare(strict_types=1);

namespace FinSearch\Tests\Findologic\Api;

use FINDOLOGIC\FinSearch\Findologic\Api\SortingService;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SortingServiceTest extends TestCase
{
    use SalesChannelHelper;
    use IntegrationTestBehaviour;

    public function legacySortingProvider(): array
    {
        // In Shopware 6.1, this parameter was called "sort".
        $orderParameter = Utils::versionLowerThan('6.2') ? 'sort' : 'order';

        return [
            'no explicit sorting provided' => [
                'request' => new Request(),
                'expectedOrder' => 'name-asc'
            ],
            'explicit sorting by "name-desc" provided' => [
                'request' => new Request([$orderParameter => 'name-desc']),
                'expectedOrder' => 'name-desc'
            ],
            'explicit unknown order provided' => [
                'request' => new Request([$orderParameter => 'i do not know']),
                'expectedOrder' => 'name-asc'
            ],
        ];
    }

    /**
     * @dataProvider legacySortingProvider
     */
    public function testProperLegacySortingIsApplied(Request $request, string $expectedOrder): void
    {
        if (Utils::versionGreaterOrEqual('6.3.3.0')) {
            $this->markTestSkipped('Legacy sorting is only used for Shopware versions lower than 6.3.3.0');
        }

        $sortingService = $this->buildSortingService();

        $productListingResult = new ProductListingResult(
            0,
            new EntityCollection(),
            new AggregationResultCollection(),
            new Criteria(),
            Context::createDefaultContext()
        );
        $event = new ProductListingResultEvent($request, $productListingResult, $this->buildSalesChannelContext());
        $sortingService->handleResult($event);

        $this->assertSame($expectedOrder, $event->getResult()->getSorting());
    }

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
        if (Utils::versionLowerThan('6.3.3.0')) {
            $this->markTestSkipped('Legacy sorting is used for Shopware versions lower than 6.3.3.0');
        }

        $sortingService = $this->buildSortingService();
        $event = new ProductListingCriteriaEvent(new Request(), new Criteria(), $this->buildSalesChannelContext());
        $searchRequestHandlerMock = $this->getMockBuilder(NavigationRequestHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event->getCriteria()->addExtension('sortings', $sortings);
        $sortingService->handleRequest($event, $searchRequestHandlerMock);

        $actualSortings = $event->getCriteria()->getExtension('sortings')->getElements();
        $this->assertCount(count($expectedSortings), $actualSortings);
    }

    private function buildSortingService(
        ?ProductListingSortingRegistry $legacySortingRegistry = null,
        TranslatorInterface $translator = null
    ): SortingService {
        $sortingRegistry = null;
        if (!$legacySortingRegistry) {
            if ($this->getContainer()->has(ProductListingSortingRegistry::class)) {
                $sortingRegistry = $this->getContainer()->get(ProductListingSortingRegistry::class);
            }
        }

        return new SortingService(
            $sortingRegistry,
            $translator ?? $this->getContainer()->get('translator'),
            $this->getContainer()->getParameter('kernel.shopware_version')
        );
    }
}
