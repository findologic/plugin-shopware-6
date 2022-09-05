<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response;

use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
use FINDOLOGIC\FinSearch\Struct\LandingPage;
use FINDOLOGIC\FinSearch\Struct\Pagination;
use FINDOLOGIC\FinSearch\Struct\Promotion;
use FINDOLOGIC\FinSearch\Struct\QueryInfoMessage\QueryInfoMessage;
use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\HttpFoundation\Request;

abstract class ResponseParser
{
    protected Response $response;

    protected ?ServiceConfigResource $serviceConfigResource;

    protected Config $config;

    public function __construct(
        Response $response,
        ?ServiceConfigResource $serviceConfigResource = null,
        ?Config $config = null
    ) {
        $this->response = $response;
        $this->serviceConfigResource = $serviceConfigResource;
        $this->config = $config;
    }

    public static function getInstance(
        Response $response,
        ?ServiceConfigResource $serviceConfigResource = null,
        ?Config $config = null
    ): ResponseParser {
        switch (true) {
            case $response instanceof Xml21Response:
                return new Xml21ResponseParser($response, $serviceConfigResource, $config);
            default:
                throw new InvalidArgumentException('Unsupported response format.');
        }
    }

    abstract public function getProductIds(): array;

    abstract public function getSmartDidYouMeanExtension(Request $request): SmartDidYouMean;

    abstract public function getLandingPageExtension(): ?LandingPage;

    abstract public function getPromotionExtension(): ?Promotion;

    abstract public function getFiltersExtension(?Client $client = null): FiltersExtension;

    abstract public function getPaginationExtension(?int $limit, ?int $offset): Pagination;

    abstract public function getQueryInfoMessage(ShopwareEvent $event): QueryInfoMessage;

    /**
     * It must be possible to select a category or vendor filter from the Smart Suggest even if that filter is disabled
     * in the filter configuration. For that we need to manually add the inactive filter from the smart suggest blocks.
     */
    abstract public function getFiltersWithSmartSuggestBlocks(
        FiltersExtension $flFilters,
        array $smartSuggestBlocks,
        array $params
    ): FiltersExtension;
}
