<?php

declare(strict_types = 1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\Tag\TagCollection;

class KeywordsAdapter
{
    /**
     * @return Keyword[]
     */
    public function adapt(ProductEntity $product): array
    {
        $keywords = $product->getSearchKeywords();

        return $this->getKeywords($keywords, $this->getBlacklistedKeywords($product));
    }

    /**
     * @return Keyword[]
     */
    private function getKeywords(?ProductSearchKeywordCollection $keywordsCollection, array $blackListedKeywords): array
    {
        $keywords = [];

        if (!$keywordsCollection || $keywordsCollection->count() <= 0) {
            return [];
        }

        foreach ($keywordsCollection as $keyword) {
            $keywordValue = $keyword->getKeyword();
            if (Utils::isEmpty($keywordValue)) {
                continue;
            }

            $isBlackListedKeyword = in_array($keywordValue, $blackListedKeywords);
            if ($isBlackListedKeyword) {
               continue;
            }

            $keywords[] = new Keyword($keywordValue);
        }

        return $keywords;
    }

    private function getBlacklistedKeywords(ProductEntity $product): array
    {
        $blackListedKeywords = [
            $product->getProductNumber(),
        ];

        if ($manufacturer = $product->getManufacturer()) {
            $blackListedKeywords[] = $manufacturer->getTranslation('name');
        }

        return $blackListedKeywords;
    }
}
