<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Api;

use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchNavigationRequestHandler;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class SortingService
{
    protected const TOPSELLER_SORT_FIELD = 'product.sales';

    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function handleRequest(
        ProductListingCriteriaEvent $event,
        SearchNavigationRequestHandler $requestHandler
    ): void {
        if ($requestHandler instanceof NavigationRequestHandler) {
            $this->addTopResultSorting($event);
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
}
