<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use function strpos;

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
    public function saveConfiguration(Request $request): Response
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $languageId = $request->query->get('languageId');
        $configs = $request->request->all();
        $this->saveKeyValues($configs, $salesChannelId, $languageId);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/finsearch/batch", name="api.action.finsearch.save.batch", methods={"POST"})
     */
    public function batchSaveConfiguration(Request $request): Response
    {
        foreach ($request->request->all() as $key => $config) {
            if (strpos($key, '-') !== false) {
                [$salesChannelId, $languageId] = explode('-', $key);
                $this->saveKeyValues($config, $salesChannelId, $languageId);
            } else {
                $this->saveKeyValues($config);
            }
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function saveKeyValues(array $config, ?string $salesChannelId = null, ?string $languageId = null): void
    {
        foreach ($config as $key => $value) {
            $this->findologicConfigService->set($key, $value, $salesChannelId, $languageId);
        }
    }
}
