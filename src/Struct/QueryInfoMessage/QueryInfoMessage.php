<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\QueryInfoMessage;

use InvalidArgumentException;
use Shopware\Core\Framework\Struct\Struct;

abstract class QueryInfoMessage extends Struct
{
    // Search results for "<query>" (<count> hits)
    public const TYPE_QUERY = 'query';
    // Search results for <cat-filter-name> <cat-name> (<count> hits)
    public const TYPE_CATEGORY = 'cat';
    // Search results for <vendor-filter-name> <vendor-name> (<count> hits)
    public const TYPE_VENDOR = 'vendor';
    // Search results for <shopping-guide> (<count> hits)
    public const TYPE_SHOPPING_GUIDE = 'wizard';
    // Search results (<count> hits)
    public const TYPE_DEFAULT = 'default';

    public static function buildInstance(
        string $type,
        ?string $query = null,
        ?string $filterName = null,
        ?string $filterValue = null
    ): self {
        switch ($type) {
            case self::TYPE_QUERY:
                static::assertQueryIsNotEmpty($query);

                return new SearchTermQueryInfoMessage($query);
            case self::TYPE_CATEGORY:
                static::assertFilterNameAndValueAreNotEmpty($filterName, $filterValue);

                return new CategoryInfoMessage($filterName, $filterValue);
            case self::TYPE_VENDOR:
                static::assertFilterNameAndValueAreNotEmpty($filterName, $filterValue);

                return new VendorInfoMessage($filterName, $filterValue);
            case self::TYPE_SHOPPING_GUIDE:
                static::assertWizardIsNotEmpty($query);

                return new ShoppingGuideInfoMessage($query);
            case self::TYPE_DEFAULT:
                return new DefaultInfoMessage();
            default:
                throw new InvalidArgumentException(sprintf('Unknown query info message type "%s".', $type));
        }
    }

    private static function assertFilterNameAndValueAreNotEmpty(?string $filterName, ?string $filterValue): void
    {
        if (!$filterName || !$filterValue) {
            throw new InvalidArgumentException('Filter name and filter value must be set!');
        }
    }

    private static function assertQueryIsNotEmpty(?string $query): void
    {
        if (!$query) {
            throw new InvalidArgumentException('Query must be set for a SearchTermQueryInfoMessage!');
        }
    }

    private static function assertWizardIsNotEmpty(?string $query): void
    {
        if (!$query) {
            throw new InvalidArgumentException('Wizard parameter must be set for a ShoppingGuideInfoMessage!');
        }
    }
}
