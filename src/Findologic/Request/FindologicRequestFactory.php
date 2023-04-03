<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request;

use FINDOLOGIC\Api\Definitions\OutputAdapter;
use FINDOLOGIC\Api\Exceptions\InvalidParamException;
use FINDOLOGIC\Api\Requests\Request as FindologicApiRequest;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\Shopware6Common\Export\Utils\Utils;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\HttpFoundation\Request;

abstract class FindologicRequestFactory
{
    private const CACHE_VERSION_LIFETIME = 60 * 60 * 24;
    private const CACHE_VERSION_KEY = 'finsearch_version';

    private CacheItemPoolInterface $cache;

    private EntityRepository $pluginRepository;

    private string $shopwareVersion;

    public function __construct(
        CacheItemPoolInterface $cache,
        EntityRepository $pluginRepository,
        string $shopwareVersion
    ) {
        $this->cache = $cache;
        $this->pluginRepository = $pluginRepository;
        $this->shopwareVersion = $shopwareVersion;
    }

    abstract public function getInstance(Request $request);

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidArgumentException
     */
    protected function setDefaults(
        Request $request,
        SearchNavigationRequest $searchNavigationRequest
    ): SearchNavigationRequest {
        $searchNavigationRequest->setUserIp($this->fetchClientIp());
        $searchNavigationRequest->setRevision($this->getPluginVersion());
        $searchNavigationRequest->setOutputAdapter(OutputAdapter::JSON_10);
        $searchNavigationRequest->addIndividualParam('shopType', 'Shopware6', FindologicApiRequest::SET_VALUE);
        $searchNavigationRequest->addIndividualParam(
            'shopVersion',
            $this->shopwareVersion,
            FindologicApiRequest::SET_VALUE
        );

        // TODO: Get the count from the shopware config. At the point of writing this, this config does not exist yet.
        //  Shopware themselves have it hardcoded at 24.
        $searchNavigationRequest->setFirst(0);
        $searchNavigationRequest->setCount(Pagination::DEFAULT_LIMIT);

        if ($request->headers->get('referer')) {
            $searchNavigationRequest->setReferer($request->headers->get('referer'));
        }

        $this->setPushAttribValues($request, $searchNavigationRequest);

        try {
            // setShopUrl() requires a valid host. If we do not have a valid host (e.g. local development)
            // this would cause an exception.
            $searchNavigationRequest->setShopUrl($request->getHost());
        } catch (InvalidParamException $e) {
            $searchNavigationRequest->setShopUrl('example.org');
        }

        return $searchNavigationRequest;
    }

    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    protected function getPluginVersion(): string
    {
        $item = $this->cache->getItem(self::CACHE_VERSION_KEY);
        if (empty($item->get())) {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('name', 'FinSearch'));

            $result = $this->pluginRepository->search($criteria, Context::createDefaultContext());

            /** @var PluginEntity $plugin */
            $plugin = $result->first();
            $item->set($plugin->getVersion());
            $item->expiresAfter(self::CACHE_VERSION_LIFETIME);

            $this->cache->save($item);
        }

        return $item->get();
    }

    private function fetchClientIp(): string
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Check for multiple IPs passing through proxy
            $position = mb_strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',');

            // If multiple IPs are passed, extract the first one
            if ($position !== false) {
                $ipAddress = mb_substr($_SERVER['HTTP_X_FORWARDED_FOR'], 0, $position);
            } else {
                $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipAddress = 'UNKNOWN';
        }

        $ipAddress = implode(',', array_unique(array_map('trim', explode(',', $ipAddress))));

        return $ipAddress;
    }

    /**
     * Findologic provides an interface to boost certain products based on their attributes, so they are ranked
     * higher in the search results.
     * The format of the parameter is:
     * `pushAttrib[key][value] = factor`
     */
    private function setPushAttribValues(Request $request, SearchNavigationRequest $searchNavigationRequest): void
    {
        $pushAttrib = $request->get('pushAttrib', []);
        if (!Utils::isEmpty($pushAttrib)) {
            foreach ($pushAttrib as $key => $attrib) {
                foreach ($attrib as $value => $factor) {
                    $searchNavigationRequest->addPushAttrib($key, $value, $factor);
                }
            }
        }
    }
}
