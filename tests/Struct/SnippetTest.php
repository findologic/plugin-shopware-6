<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Struct;

use FINDOLOGIC\FinSearch\Struct\Snippet;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

class SnippetTest extends TestCase
{
    public function testSnippetReceivesParamsAsExpected(): void
    {
        $expectedShopkey = 'AB12AB12AB12AB12AB12AB12AB12AB12';
        $expectedSearchResultContainer = 'fl-special';
        $expectedNavigationResultContainer = 'fl-special-navigation';
        $expectedUserGroupHash = Utils::calculateUserGroupHash($expectedShopkey, Uuid::randomHex());

        $snippet = new Snippet(
            $expectedShopkey,
            $expectedSearchResultContainer,
            $expectedNavigationResultContainer,
            $expectedUserGroupHash
        );

        $this->assertSame($expectedShopkey, $snippet->getShopkey());
        $this->assertSame($expectedSearchResultContainer, $snippet->getSearchResultContainer());
        $this->assertSame($expectedNavigationResultContainer, $snippet->getNavigationResultContainer());
        $this->assertSame($expectedUserGroupHash, $snippet->getUserGroupHash());
    }

    public function testSnippetCanBeProperlySerialized(): void
    {
        $snippet = unserialize(serialize(new Snippet(
            '56785678567856785678567856785678',
            'search-result-container',
            'navigation-result-container',
            'yikes'
        )));

        $this->assertInstanceOf(Snippet::class, $snippet);
    }
}
