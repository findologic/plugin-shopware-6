<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Definitions\OutputAdapter;
use FINDOLOGIC\Api\Definitions\QueryParameter;
use FINDOLOGIC\Api\Exceptions\InvalidParamException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\HttpFoundation\Request;

class SearchRequestFactory
{
    private const
        CACHE_VERSION_LIFETIME = 60 * 60 * 24,
        CACHE_VERSION_KEY = 'finsearch_version';

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var ContainerInterface */
    private $container;

    public function __construct(CacheItemPoolInterface $cache, ContainerInterface $container)
    {
        $this->cache = $cache;
        $this->container = $container;
    }

    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    private function getPluginVersion(): string
    {
        $item = $this->cache->getItem(self::CACHE_VERSION_KEY);
        if (empty($item->get())) {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('name', 'FinSearch'));

            $result = $this->container->get('plugin.repository')->search($criteria, Context::createDefaultContext());

            /** @var PluginEntity $plugin */
            $plugin = $result->first();
            $item->set($plugin->getVersion());
            $item->expiresAfter(self::CACHE_VERSION_LIFETIME);

            $this->cache->save($item);
        }

        return $item->get();
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidArgumentException
     */
    public function getInstance(Request $request): SearchRequest
    {
        $searchRequest = new SearchRequest();
        $searchRequest->setUserIp($request->getClientIp());
        $searchRequest->setReferer($request->headers->get('referer'));
        $searchRequest->setRevision($this->getPluginVersion());
        $searchRequest->setOutputAdapter(OutputAdapter::XML_21);

        if ($request->get(QueryParameter::FORCE_ORIGINAL_QUERY, -1) !== -1) {
            $searchRequest->setForceOriginalQuery();
        }

        try {
            $searchRequest->setShopUrl($request->getHost());
        } catch (InvalidParamException $e) {
            $searchRequest->setShopUrl('example.org');
        }

        return $searchRequest;
    }
}
