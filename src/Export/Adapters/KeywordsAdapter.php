<?php

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\Tag\TagCollection;

class KeywordsAdapter
{
    /**
     * @return Keyword[]
     */
    public function adapt(ProductEntity $product): array
    {
        $tags = $product->getTags();

        if ($tags === null || $tags->count() <= 0) {
            return [];
        }

        return $this->getKeywords($tags);
    }

    /**
     * @return Keyword[]
     */
    private function getKeywords(TagCollection $tags): array
    {
        $keywords = [];

        foreach ($tags as $tag) {
            if (Utils::isEmpty($tag->getName())) {
                continue;
            }

            $keywords[] = new Keyword($tag->getName());
        }

        return $keywords;
    }
}
