<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilder as ShopwareProductSearchBuilder;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
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
        if ($request->getPathInfo() === '/suggest') {
            $this->buildParent($request, $criteria, $context);
            return;
        }

        $search = $request->query->get('search');

        if (is_array($search)) {
            $term = implode(' ', $search);
        } else {
            $term = (string)$search;
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

    public function buildParent(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        parent::build($request, $criteria, $context);
    }
}
