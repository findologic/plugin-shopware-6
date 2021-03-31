<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use InvalidArgumentException;
use PackageVersions\Versions;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
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

        return trim($result) ?? trim($string);
    }

    public static function removeSpecialChars(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return preg_replace('/[^äöüA-Za-z0-9:_-]/u', '', $string);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public static function addProductAssociations(Criteria $criteria): Criteria
    {
        return $criteria->addAssociations(
            [
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
            ]
        );
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
        return sprintf(
            '%s%s%s%s%s%s%s%s%s%s',
            isset($parsedUrl['scheme']) ? "{$parsedUrl['scheme']}:" : '',
            (isset($parsedUrl['user']) || isset($parsedUrl['host'])) ? '//' : '',
            isset($parsedUrl['user']) ? "{$parsedUrl['user']}" : '',
            isset($parsedUrl['pass']) ? ":{$parsedUrl['pass']}" : '',
            isset($parsedUrl['user']) ? '@' : '',
            isset($parsedUrl['host']) ? "{$parsedUrl['host']}" : '',
            isset($parsedUrl['port']) ? ":{$parsedUrl['port']}" : '',
            isset($parsedUrl['path']) ? "{$parsedUrl['path']}" : '',
            isset($parsedUrl['query']) ? "?{$parsedUrl['query']}" : '',
            isset($parsedUrl['fragment']) ? "#{$parsedUrl['fragment']}" : ''
        );
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

        // Development versions may add the versions with an "@" sign, which refers to the current commit.
        $versionWithoutCommitHash = substr($shopwareVersion, 0, strpos($shopwareVersion, '@'));

        // Development versions may add the versions with an "-" sign, which refers to the stability of the release.
        // E.g. 6.4.0.0-RC1.
        $versionWithoutReleaseInformation = substr($shopwareVersion, 0, strpos($versionWithoutCommitHash, '-'));

        return version_compare($versionWithoutReleaseInformation, $version, '<');
    }

    /**
     * Determines based on the current request and settings, if the Findologic Search should be handle the request
     * or not. In case this method has already been called once, the same state will be returned.
     */
    public static function shouldHandleRequest(
        Request $request,
        Context $context,
        ServiceConfigResource $serviceConfigResource,
        Config $config,
        bool $isCategoryPage = false
    ): bool {
        /** @var FindologicService $findologicService */
        $findologicService = $context->getExtension('findologicService');
        if ($findologicService && $findologicService->getEnabled()) {
            return $findologicService->getEnabled();
        }

        if (!$config->isInitialized()) {
            throw new InvalidArgumentException('Config needs to be initialized first!');
        }

        $findologicService = new FindologicService();
        $context->addExtension('findologicService', $findologicService);

        $shopkey = $config->getShopkey();
        if (!$shopkey || trim($shopkey) === '') {
            return $findologicService->disable();
        }

        $isDirectIntegration = $serviceConfigResource->isDirectIntegration($shopkey);
        $isStagingShop = $serviceConfigResource->isStaging($shopkey);
        $isStagingSession = static::isStagingSession($request);

        // Allow request if shop is not staging or is staging with findologic=on flag set
        $allowRequestForStaging = (!$isStagingShop || ($isStagingShop && $isStagingSession));

        if ($config->isActive() && ($isDirectIntegration || $allowRequestForStaging)) {
            $findologicService->enableSmartSuggest();
        }

        if (!$config->isActive() || ($isCategoryPage && !$config->isActiveOnCategoryPages())) {
            return $findologicService->disable();
        }

        if ($isDirectIntegration || !$allowRequestForStaging) {
            return $findologicService->disable();
        }

        return $findologicService->enable();
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

    public static function isFindologicEnabled(SalesChannelContext $context): bool
    {
        /** @var FindologicService $findologicService */
        $findologicService = $context->getContext()->getExtension('findologicService');

        return $findologicService ? $findologicService->getEnabled() : false;
    }

    public static function isEmpty($value): bool
    {
        if (is_numeric($value) || is_object($value) || is_bool($value)) {
            return false;
        }

        if (is_array($value) && empty(array_filter($value))) {
            return true;
        }

        if (is_string($value) && empty(trim($value))) {
            return true;
        }

        if (empty($value)) {
            return true;
        }

        return false;
    }

    public static function buildCategoryPath(array $categoryBreadCrumb, CategoryEntity $rootCategory): string
    {
        $breadcrumb = static::getCategoryBreadcrumb($categoryBreadCrumb, $rootCategory);

        // Build category path and trim all entries.
        return implode('_', array_map('trim', $breadcrumb));
    }

    /**
     * Builds the category path by removing the path of the parent (root) category of the sales channel.
     * Since Findologic does not care about any root categories, we need to get the difference between the
     * normal category path and the root category.
     *
     * @return string[]
     */
    private static function getCategoryBreadcrumb(array $categoryBreadcrumb, CategoryEntity $rootCategory): array
    {
        $rootCategoryBreadcrumbs = $rootCategory->getBreadcrumb();

        $path = array_splice($categoryBreadcrumb, count($rootCategoryBreadcrumbs));

        return array_values($path);
    }

    public static function fetchNavigationCategoryFromSalesChannel(
        EntityRepository $categoryRepository,
        SalesChannelEntity $salesChannel
    ): ?CategoryEntity {
        $navigationCategory = $salesChannel->getNavigationCategory();
        if (!$navigationCategory) {
            $result = $categoryRepository->search(
                new Criteria([$salesChannel->getNavigationCategoryId()]),
                Context::createDefaultContext()
            );

            $navigationCategory = $result->first();
        }

        return $navigationCategory;
    }
}
