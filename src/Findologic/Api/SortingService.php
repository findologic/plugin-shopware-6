<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Api;

use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchNavigationRequestHandler;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SortingService
{
    protected const TOPSELLER_SORT_FIELD = 'product.sales';

    /** @var ProductListingSortingRegistry|null */
    private $legacySortingRegistry;

    /** @var TranslatorInterface */
    private $translator;

    /** @var string */
    private $shopwareVersion;

    public function __construct(
        ?ProductListingSortingRegistry $legacySortingRegistry,
        TranslatorInterface $translator,
        string $shopwareVersion
    ) {
        $this->legacySortingRegistry = $legacySortingRegistry;
        $this->translator = $translator;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function handleRequest(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler
    ): void {
        if (
            $requestHandler instanceof NavigationRequestHandler &&
            Utils::versionGreaterOrEqual('6.3.3.0', $this->shopwareVersion)
        ) {
            $this->addTopResultSorting($event);
        }
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        if (Utils::versionLowerThan('6.3.3.0', $this->shopwareVersion)) {
            $this->addLegacyTopResultSorting($event);
        }
    }

    protected function addTopResultSorting(ProductListingCriteriaEvent $event): void
    {
        /** @var ProductSortingCollection $availableSortings */
        $availableSortings = $event->getCriteria()->getExtension('sortings') ?? new ProductSortingCollection();
        if ($this->hasTopSellerSorting($availableSortings)) {
            return;
        }

        $sortByScore = new ProductSortingEntity();
        $sortByScore->setId(Uuid::randomHex());
        $sortByScore->setActive(true);
        $sortByScore->setTranslated(['label' => $this->translator->trans('filter.sortByScore')]);
        $sortByScore->setKey('score');
        $sortByScore->setPriority(5);
        $sortByScore->setFields([
            [
                'field' => '_score',
                'order' => 'desc',
                'priority' => 1,
                'naturalSorting' => 0,
            ],
        ]);

        $availableSortings->add($sortByScore);

        $event->getCriteria()->addExtension('sortings', $availableSortings);
    }

    protected function hasTopSellerSorting(ProductSortingCollection $sortings): bool
    {
        $topsellerSortings = array_filter($sortings->getElements(), function (ProductSortingEntity $sort) {
            foreach ($sort->getFields() as $field) {
                if (!isset($field['field']) || $field['field'] !== self::TOPSELLER_SORT_FIELD) {
                    continue;
                }

                return true;
            }

            return false;
        });

        return $topsellerSortings !== [];
    }

    protected function addLegacyTopResultSorting(ProductListingResultEvent $event): void
    {
        $currentSorting = $this->getCurrentLegacySorting(
            $event->getRequest(),
            $this->getDefaultSort()
        );

        $event->getResult()->setSorting($currentSorting);
        $this->legacySortingRegistry->add(
            new ProductListingSorting('score', 'filter.sortByScore', ['_score' => 'desc'])
        );
        $sortings = $this->legacySortingRegistry->getSortings();
        /** @var ProductListingSorting $sorting */
        foreach ($sortings as $sorting) {
            $sorting->setActive($sorting->getKey() === $currentSorting);
        }

        $event->getResult()->setSortings($sortings);
    }

    protected function getCurrentLegacySorting(Request $request, string $default): ?string
    {
        $key = $request->get('order', $default);
        if (Utils::versionLowerThan('6.2', $this->shopwareVersion)) {
            $key = $request->get('sort', $default);
        }

        if (!$key) {
            return null;
        }

        if ($this->legacySortingRegistry->has($key)) {
            return $key;
        }

        return $default;
    }

    protected function getDefaultSort(): string
    {
        $legacyDefaultSortConstName =
            '\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber::DEFAULT_SORT';

        if (defined($legacyDefaultSortConstName)) {
            return ProductListingFeaturesSubscriber::DEFAULT_SORT;
        }

        return 'name-asc';
    }
}
