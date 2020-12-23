<?php

declare(strict_types=1);

namespace FinSearch\Tests\Findologic\Api;

use FINDOLOGIC\FinSearch\Findologic\Api\SortingService;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SortingServiceTest extends TestCase
{
    use SalesChannelHelper;
    use IntegrationTestBehaviour;

    public function legacySortingProvider(): array
    {
        return [
            'no explicit sorting provided' => [
                'request' => new Request(),
                'expectedOrder' => 'name-asc'
            ],
            'explicit sorting by "_score" provided' => [
                'request' => new Request(['order' => 'score']),
                'expectedOrder' => 'score'
            ],
            'explicit unknown order provided' => [
                'request' => new Request(['order' => 'i do not know']),
                'expectedOrder' => 'name-asc'
            ],
        ];
    }

    /**
     * @dataProvider legacySortingProvider
     */
    public function testProperLegacySortingIsApplied(Request $request, string $expectedOrder): void
    {
        if (!Utils::versionLowerThan('6.3.3.0')) {
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

    private function buildSortingService(
        ?ProductListingSortingRegistry $legacySortingRegistry = null,
        TranslatorInterface $translator = null
    ): SortingService {
        return new SortingService(
            $legacySortingRegistry ?? $this->getContainer()->get(ProductListingSortingRegistry::class),
            $translator ?? $this->getContainer()->get('translator')
        );
    }
}
