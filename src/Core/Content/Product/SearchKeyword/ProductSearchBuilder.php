<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SearchKeyword;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilder as ShopwareProductSearchBuilder;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductSearchBuilder extends ShopwareProductSearchBuilder
{
    /**
     * @var ProductSearchTermInterpreterInterface
     */
    private $interpreter;

    public function __construct(ProductSearchTermInterpreterInterface $interpreter)
    {
        parent::__construct($interpreter);
        $this->interpreter = $interpreter;
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (Utils::versionLowerThan('6.4.0.0')) {
            $this->buildShopware63AndLower($request, $criteria, $context);
        } else {
            $this->buildShopware64AndGreater($request, $criteria, $context);
        }
    }

    public function buildParent(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        parent::build($request, $criteria, $context);
    }

    private function buildShopware63AndLower(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if ($request->getPathInfo() === '/suggest') {
            $this->buildParent($request, $criteria, $context);
            return;
        }

        $search = $request->query->get('search');

        if (is_array($search)) {
            $term = implode(' ', $search);
        } else {
            $term = (string) $search;
        }

        $term = trim($term);

        $pattern = $this->interpreter->interpret($term, $context->getContext());

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new EqualsFilter('product.searchKeywords.keyword', $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    'product.searchKeywords.ranking'
                )
            );
        }
        $criteria->addQuery(
            new ScoreQuery(
                new ContainsFilter('product.searchKeywords.keyword', $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                'product.searchKeywords.ranking'
            )
        );

        $criteria->addFilter(
            new EqualsAnyFilter('product.searchKeywords.keyword', array_values($pattern->getAllTerms()))
        );
        $criteria->addFilter(
            new EqualsFilter('product.searchKeywords.languageId', $context->getContext()->getLanguageId())
        );
    }

    private function buildShopware64AndGreater(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if ($request->getPathInfo() === '/suggest') {
            $this->buildParent($request, $criteria, $context);
            return;
        }

        $search = $request->query->get('search');

        if (is_array($search)) {
            $term = implode(' ', $search);
        } else {
            $term = (string) $search;
        }

        $term = trim($term);

        $pattern = $this->interpreter->interpret($term, $context->getContext());

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new EqualsFilter('product.searchKeywords.keyword', $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    'product.searchKeywords.ranking'
                )
            );
        }
        $criteria->addQuery(
            new ScoreQuery(
                new ContainsFilter('product.searchKeywords.keyword', $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                'product.searchKeywords.ranking'
            )
        );

        if ($pattern->getBooleanClause() !== SearchPattern::BOOLEAN_CLAUSE_AND) {
            $criteria->addFilter(new AndFilter([
                new EqualsAnyFilter('product.searchKeywords.keyword', array_values($pattern->getAllTerms())),
                new EqualsFilter('product.searchKeywords.languageId', $context->getContext()->getLanguageId()),
            ]));
            return;
        }

        foreach ($pattern->getTokenTerms() as $terms) {
            $criteria->addFilter(new AndFilter([
                new EqualsFilter('product.searchKeywords.languageId', $context->getContext()->getLanguageId()),
                new EqualsAnyFilter('product.searchKeywords.keyword', $terms),
            ]));
        }
    }
}
