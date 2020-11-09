<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use Exception;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class FindologicConfigController extends AbstractController
{
    /**
     * @var FindologicConfigService
     */
    private $findologicConfigService;

    public function __construct(FindologicConfigService $findologicConfigService)
    {
        $this->findologicConfigService = $findologicConfigService;
    }

    /**
     * @Route("/api/v{version}/_action/finsearch", name="api.action.finsearch", methods={"GET"})
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
     * @Route("/api/v{version}/_action/finsearch", name="api.action.finsearch.save", methods={"POST"})
     */
    public function saveConfiguration(Request $request): NoContentResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $languageId = $request->query->get('languageId');
        $configs = $request->request->all();
        $this->saveKeyValues($salesChannelId, $languageId, $configs);

        // return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        return new NoContentResponse();
    }

    /**
     * @Route("/api/v{version}/_action/finsearch/batch", name="api.action.finsearch.save.batch", methods={"POST"})
     */
    public function batchSaveConfiguration(Request $request): NoContentResponse
    {
        foreach ($request->request->all() as $key => $config) {
            [$salesChannelId, $languageId] = explode('-', $key);
            $this->saveKeyValues($salesChannelId, $languageId, $config);
        }

        return new NoContentResponse();
    }

    private function saveKeyValues(string $salesChannelId, string $languageId, array $config): void
    {
        foreach ($config as $key => $value) {
            $this->findologicConfigService->set($key, $value, $salesChannelId, $languageId);
        }
    }
}
