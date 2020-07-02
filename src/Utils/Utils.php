<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

use FINDOLOGIC\FinSearch\Struct\FindologicEnabled;
use PackageVersions\Versions;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

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

    public static function removeControlCharacters(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        $result = preg_replace('/[\x{0000}-\x{001F}]|[\x{007F}]|[\x{0080}-\x{009F}]/u', '', $string);

        return $result ?? $string;
    }

    public static function removeSpecialChars(string $string): string
    {
        return preg_replace('/[^äöüA-Za-z0-9:_-]/u', '', $string);
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
            'children.properties.productConfiguratorSettings.option.group.translations',
        ];

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $criteria;
    }

    public static function multiByteRawUrlEncode(string $string): string
    {
        $encoded = '';
        $length = mb_strlen($string);
        for ($i = 0; $i < $length; ++$i) {
            $encoded .= '%' . wordwrap(bin2hex(mb_substr($string, $i, 1)), 2, '%', true);
        }

        return $encoded;
    }

    public static function buildUrl(array $parsedUrl): string
    {
        return (isset($parsedUrl['scheme']) ? "{$parsedUrl['scheme']}:" : '')
            . ((isset($parsedUrl['user']) || isset($parsedUrl['host'])) ? '//' : '')
            . (isset($parsedUrl['user']) ? "{$parsedUrl['user']}" : '')
            . (isset($parsedUrl['pass']) ? ":{$parsedUrl['pass']}" : '')
            . (isset($parsedUrl['user']) ? '@' : '')
            . (isset($parsedUrl['host']) ? "{$parsedUrl['host']}" : '')
            . (isset($parsedUrl['port']) ? ":{$parsedUrl['port']}" : '')
            . (isset($parsedUrl['path']) ? "{$parsedUrl['path']}" : '')
            . (isset($parsedUrl['query']) ? "?{$parsedUrl['query']}" : '')
            . (isset($parsedUrl['fragment']) ? "#{$parsedUrl['fragment']}" : '');
    }

    public static function versionLowerThan(string $version): bool
    {
        $versions = Versions::VERSIONS;
        if (isset($versions['shopware/core'])) {
            $shopwareVersion = Versions::getVersion('shopware/core');
        } else {
            $shopwareVersion = Versions::getVersion('shopware/platform');
        }
        // Trim the version if it has v6.x.x instead of 6.x.x so it can be compared correctly.
        $shopwareVersion = ltrim($shopwareVersion, 'v');

        return version_compare($shopwareVersion, $version, '<');
    }

    public static function isFindologicEnabled(SalesChannelContext $context): bool
    {
        /** @var FindologicEnabled $findologicEnabled */
        $findologicEnabled = $context->getContext()->getExtension('flEnabled');

        return $findologicEnabled ? $findologicEnabled->getEnabled() : false;
    }

    public static function isStagingSession(Request $request): bool
    {
        $findologic = $request->get('findologic');
        if ($findologic === 'on') {
            $request->getSession()->set('stagingFlag', true);

            return true;
        }

        if ($findologic === 'off' || $findologic === 'disabled') {
            $request->getSession()->set('stagingFlag', false);

            return false;
        }

        if ($request->getSession()->get('stagingFlag') === true) {
            return true;
        }

        return false;
    }
}
