<?php

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\Shopware6Common\Export\Adapters\KeywordsAdapter as CommonKeywordsAdapter;
use FINDOLOGIC\Shopware6Common\Export\Utils\Utils;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class KeywordsAdapter extends CommonKeywordsAdapter
{
    protected SalesChannelContext $salesChannelContext;

    public function __construct(
        SalesChannelContext $salesChannelContext
    ) {
        $this->salesChannelContext = $salesChannelContext;
    }

    protected function getKeywords(
        ?array $keywordsCollection,
        array $blackListedKeywords
    ): array {

        $keywords = [];

        if (!$keywordsCollection || count($keywordsCollection) <= 0) {
            return [];
        }

        foreach ($keywordsCollection as $keyword) {
            $keywordValue = $keyword->keyword;
            if (Utils::isEmpty($keywordValue)) {
                continue;
            }

            $isBlackListedKeyword = in_array($keywordValue, $blackListedKeywords);
            if ($isBlackListedKeyword) {
                continue;
            }

            if ($this->salesChannelContext->getLanguageId() != $keyword->languageId) {
                continue;
            }

            $keywords[] = new Keyword($keywordValue);
        }

        return $keywords;
    }
}
