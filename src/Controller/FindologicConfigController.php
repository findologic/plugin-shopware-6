<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use Doctrine\DBAL\Connection;
use FINDOLOGIC\FinSearch\Exceptions\Config\ShopkeyAlreadyExistsException;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use function in_array;

class FindologicConfigController extends AbstractController
{
    public function __construct(
        private readonly FindologicConfigService $findologicConfigService,
        private readonly Connection $connection
    ) {
    }

    /**
     * @Route(
     *     "/api/_action/finsearch",
     *     name="api.action.finsearch",
     *     methods={"GET"},
     *     defaults={"_routeScope"={"api"}}
     * )
     * @Route(
     *     "/api/v{version}/_action/finsearch",
     *     name="api.action.finsearch.legacy",
     *     methods={"GET"},
     *     defaults={"_routeScope"={"api"}}
     * )
     */
    public function getConfigurationValues(Request $request): JsonResponse
    {
        $languageId = $request->query->get('languageId');
        $salesChannelId = $request->query->get('salesChannelId');

        $config = $this->findologicConfigService->getConfig($salesChannelId, $languageId);

        if (empty($config)) {
            $json = '{}';
        } else {
            $json = json_encode($config, JSON_PRESERVE_ZERO_FRACTION);
        }

        return new JsonResponse($json, 200, [], true);
    }

    /**
     * @Route(
     *     "/api/_action/finsearch",
     *     name="api.action.finsearch.save",
     *     methods={"POST"},
     *     defaults={"_routeScope"={"api"}}
     * )
     * @Route(
     *     "/api/v{version}/_action/finsearch",
     *     name="api.action.finsearch.legacy.save",
     *     methods={"POST"},
     *     defaults={"_routeScope"={"api"}}
     * )
     */
    public function saveConfiguration(Request $request): Response
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $languageId = $request->query->get('languageId');
        $configs = $request->request->all();
        $this->saveKeyValues($salesChannelId, $languageId, $configs);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route(
     *     "/api/_action/finsearch/batch",
     *     name="api.action.finsearch.save.batch",
     *     methods={"POST"},
     *     defaults={"_routeScope"={"api"}}
     * )
     * @Route(
     *     "/api/v{version}/_action/finsearch/batch",
     *     name="api.action.finsearch.legacy.save.batch",
     *     methods={"POST"}
     * )
     */
    public function batchSaveConfiguration(Request $request): Response
    {
        $allShopkeys = [];
        $this->connection->beginTransaction();
        try {
            foreach ($request->request->all() as $key => $config) {
                [$salesChannelId, $languageId] = explode('-', $key);

                if (isset($config['FinSearch.config.shopkey']) && $config['FinSearch.config.shopkey']) {
                    $shopkey = $config['FinSearch.config.shopkey'];
                    if (!in_array($shopkey, $allShopkeys)) {
                        $allShopkeys[] = $shopkey;
                    } else {
                        throw new ShopkeyAlreadyExistsException();
                    }
                }

                $this->saveKeyValues($salesChannelId, $languageId, $config);
            }
        } catch (ShopkeyAlreadyExistsException $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $this->connection->commit();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function saveKeyValues(string $salesChannelId, string $languageId, array $config): void
    {
        foreach ($config as $key => $value) {
            $this->findologicConfigService->set($key, $value, $salesChannelId, $languageId);
        }
    }
}
