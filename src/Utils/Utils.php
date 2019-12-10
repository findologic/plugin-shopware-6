<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class Utils
{
    public static function calculateUserGroupHash(string $shopkey, string $customerGroupId): string
    {
        return base64_encode($shopkey ^ $customerGroupId);
    }

    public static function cleanString(?string $string): ?string
    {
        if (!$string) {
            return null;
        }
        $string = str_replace('\\', '', addslashes(strip_tags($string)));
        $string = str_replace(["\n", "\r", "\t"], ' ', $string);
        // Remove unprintable characters since they would cause an invalid XML.
        $string = self::removeControlCharacters($string);

        return trim($string);
    }

    public static function removeControlCharacters(string $string): string
    {
        $result = preg_replace('/[\x{0000}-\x{001F}]|[\x{007F}]|[\x{0080}-\x{009F}]/u', '', $string);

        return $result ?? $string;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public static function addProductAssociations(Criteria $criteria): Criteria
    {
        $associations = [
            'seoUrls',
            'categories',
            'categories.seoUrls',
            'translations',
            'tags',
            'media',
            'manufacturer',
            'manufacturer.translations',
            'properties',
            'properties.group',
            'properties.productConfiguratorSettings',
            'properties.productConfiguratorSettings.option',
            'properties.productConfiguratorSettings.option.group',
            'properties.productConfiguratorSettings.option.group.translations',
            'children',
            'children.properties',
            'children.properties.group',
            'children.properties.productConfiguratorSettings',
            'children.properties.productConfiguratorSettings.option',
            'children.properties.productConfiguratorSettings.option.group',
            'children.properties.productConfiguratorSettings.option.group.translations'
        ];

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $criteria;
    }
}
