<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

use Composer\InstalledVersions;
use Exception;
use FINDOLOGIC\FinSearch\Definitions\Defaults;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use InvalidArgumentException;
use PackageVersions\Versions;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Vin\ShopwareSdk\Data\Collection as SdkCollection;
use Vin\ShopwareSdk\Data\Entity\Entity as SdkEntity;

class Utils
{
    public static function versionLowerThan(string $compareVersion, ?string $actualVersion = null): bool
    {
        return version_compare(static::getCleanShopwareVersion($actualVersion), $compareVersion, '<');
    }

    public static function versionGreaterOrEqual(string $compareVersion, ?string $actualVersion = null): bool
    {
        return version_compare(static::getCleanShopwareVersion($actualVersion), $compareVersion, '>=');
    }

    public static function getCleanShopwareVersion(?string $actualVersion = null): string
    {
        // The fallback version does not include the major version for 6.2, therefore version_compare fails
        // It is 9999999-dev in 6.2 and 6.x.9999999.9999999-dev starting from 6.3
        $version = $actualVersion === Kernel::SHOPWARE_FALLBACK_VERSION
            ? static::getShopwareVersion()
            : $actualVersion ?? static::getShopwareVersion();
        $versionWithoutPrefix = ltrim($version, 'v');

        return static::cleanVersionCommitHashAndReleaseInformation($versionWithoutPrefix);
    }

    /**
     * Fetches the raw installed Shopware version. The returned version string may contain a version prefix
     * and/or a commit hash and/or release information such as "-RC1". E.g.
     * * 6.3.5.3@940439ea951dfcf7b34584485cf6251c49640cdf
     * * v6.2.3
     * * v6.4.0-RC1@34abab343847384934334781abababababcdddddd
     */
    protected static function getShopwareVersion(): string
    {
        // Composer 2 runtime API uses the `InstalledVersions::class` in favor of the
        // deprecated/removed `Versions::class`
        if (class_exists(InstalledVersions::class)) {
            if (InstalledVersions::isInstalled('shopware/platform')) {
                if (InstalledVersions::getPrettyVersion('shopware/platform')) {
                    return InstalledVersions::getPrettyVersion('shopware/platform');
                }
            }

            if (InstalledVersions::isInstalled('shopware/core')) {
                if (InstalledVersions::getPrettyVersion('shopware/core')) {
                    return InstalledVersions::getPrettyVersion('shopware/core');
                }
            }
        }

        if (defined('PackageVersions\Versions::VERSIONS')) {
            $packageVersions = Versions::VERSIONS;
            if (isset($packageVersions['shopware/platform'])) {
                return $packageVersions['shopware/platform'];
            }

            if (isset($packageVersions['shopware/core'])) {
                return $packageVersions['shopware/core'];
            }
        }

        throw new Exception('Used Shopware version cannot be detected');
    }

    protected static function cleanVersionCommitHashAndReleaseInformation(string $version): string
    {
        $hasCommitHash = !!strpos($version, '@');
        if ($hasCommitHash) {
            $version = substr($version, 0, strpos($version, '@'));
        }

        $hasReleaseInformation = !!strpos($version, '-RC');
        if ($hasReleaseInformation) {
            $version = substr($version, 0, strpos($version, '-RC'));
        }

        return $version;
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

        return $findologicService && $findologicService->getEnabled();
    }

    public static function disableFindologicWhenEnabled(SalesChannelContext $context): void
    {
        if (!static::isFindologicEnabled($context)) {
            return;
        }

        if (!$context->getContext()->hasExtension('findologicService')) {
            return;
        }

        /** @var FindologicService $findologicService */
        $findologicService = $context->getContext()->getExtension('findologicService');
        $findologicService->disable();
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

    /**
     * Builds an entity search result, which is backwards-compatible for older Shopware versions.
     */
    public static function buildEntitySearchResult(
        string $entity,
        int $total,
        EntityCollection $entities,
        ?AggregationResultCollection $aggregations,
        Criteria $criteria,
        Context $context
    ): EntitySearchResult {
        return new EntitySearchResult(
            $entity,
            $total,
            $entities,
            $aggregations,
            $criteria,
            $context,
        );
    }

    /**
     * Takes a given domain collection and only returns domains which are not associated to a headless sales
     * channel, as these do not have real URLs, but only contain placeholder information.
     */
    public static function filterSalesChannelDomainsWithoutHeadlessDomain(
        SalesChannelDomainCollection $original
    ): SalesChannelDomainCollection {
        return $original->filter(function (SalesChannelDomainEntity $domainEntity) {
            return !str_starts_with($domainEntity->getUrl(), Defaults::HEADLESS_SALES_CHANNEL_PREFIX);
        });
    }

    public static function createSdkEntity(string $sdkEntityClass, Entity $entity): SdkEntity
    {
        return SdkEntity::createFromArray($sdkEntityClass, self::serializeStruct($entity));
    }

    public static function createSdkCollection(
        string $sdkCollectionClass,
        string $sdkEntityClass,
        EntityCollection $collection
    ): SdkCollection {
        /** @var SdkCollection $sdkCollection */
        $sdkCollection = new $sdkCollectionClass();

        /** @var Entity $item */
        foreach ($collection as $item) {
            $sdkItem = Utils::createSdkEntity($sdkEntityClass, $item);
            $sdkCollection->add($sdkItem);
        }

        return $sdkCollection;
    }

    private static function serializeStruct(Struct $struct): array
    {
        $data = $struct->jsonSerialize();

        foreach ($data as $key => $value) {
            if ($value instanceof Struct) {
                $data[$key] = self::serializeStruct($value);
            }
        }

        return $data;
    }
}
