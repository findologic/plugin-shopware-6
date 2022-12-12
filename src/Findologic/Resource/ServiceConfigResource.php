<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Resource;

use DateTime;
use FINDOLOGIC\FinSearch\Findologic\Api\ServiceConfig;
use FINDOLOGIC\FinSearch\Findologic\Client\ServiceConfigClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use InvalidArgumentException as InvalidServiceConfigKeyException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class ServiceConfigResource
{
    private const CACHE_KEY = 'finsearch_serviceconfig';

    private CacheItemPoolInterface $cache;

    private ServiceConfigClientFactory $serviceConfigClientFactory;

    private ?Client $client;

    public function __construct(
        CacheItemPoolInterface $cache,
        ServiceConfigClientFactory $serviceConfigClientFactory,
        ?Client $client = null
    ) {
        $this->cache = $cache;
        $this->serviceConfigClientFactory = $serviceConfigClientFactory;
        $this->client = $client;
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientException
     */
    public function isDirectIntegration(?string $shopkey): bool
    {
        if ($shopkey === null) {
            return false;
        }

        $directIntegration = $this->get($shopkey, 'directIntegration');

        return $directIntegration['enabled'];
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientException
     */
    public function isStaging(?string $shopkey): bool
    {
        if ($shopkey === null) {
            return false;
        }

        return $this->get($shopkey, 'isStagingShop');
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientException
     */
    public function getSmartSuggestBlocks(?string $shopkey): array
    {
        return $this->get($shopkey, 'blocks');
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getFromCache(string $shopkey): ?ServiceConfig
    {
        $config = $this->cache->getItem(self::CACHE_KEY . '_' . $shopkey)->get();
        if ($config !== null) {
            return unserialize($config, [ServiceConfig::class]);
        }

        return null;
    }

    private function isExpired(ServiceConfig $serviceConfig): bool
    {
        return new DateTime() > $serviceConfig->getExpireDateTime();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function saveToCache(ServiceConfig $serviceConfig, string $shopkey): void
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY . '_' . $shopkey)->set(serialize($serviceConfig));
        $this->cache->save($cacheItem);
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientException
     */
    private function get(string $shopkey, string $key)
    {
        $serviceConfig = $this->getFromCache($shopkey);
        if ($serviceConfig === null || $this->isExpired($serviceConfig)) {
            $serviceConfigClient = $this->serviceConfigClientFactory->getInstance($shopkey, $this->client);
            $serviceConfig = new ServiceConfig();
            $serviceConfig->assign($serviceConfigClient->get());
            $this->saveToCache($serviceConfig, $shopkey);
        }

        if (is_callable([$serviceConfig, "get$key"])) {
            return $serviceConfig->{"get$key"}();
        }

        throw new InvalidServiceConfigKeyException(sprintf('Trying to access unknown ServiceConfig key "%s"', $key));
    }
}
