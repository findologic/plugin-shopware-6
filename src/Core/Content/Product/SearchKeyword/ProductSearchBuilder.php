<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
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

class ProductSearchBuilder implements ProductSearchBuilderInterface
{
    public function __construct(
        private readonly ProductSearchTermInterpreterInterface $interpreter,
        private readonly ProductSearchBuilderInterface $decorated
    ) {
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if ($request->getPathInfo() === '/suggest') {
            $this->buildParent($request, $criteria, $context);
            return;
        }

        $this->doBuild($request, $criteria, $context);
    }

    public function buildParent(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $this->decorated->build($request, $criteria, $context);
    }

    public function doBuild(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
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
