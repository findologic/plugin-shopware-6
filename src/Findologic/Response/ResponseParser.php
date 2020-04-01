<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response;

use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Struct\FiltersExtension;
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
    /** @var Response */
    protected $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public static function getInstance(Response $response): ResponseParser
    {
        switch (true) {
            case $response instanceof Xml21Response:
                return new Xml21ResponseParser($response);
            default:
                throw new InvalidArgumentException('Unsupported response format.');
        }
    }

    abstract public function getProductIds(): array;

    abstract public function getSmartDidYouMeanExtension(Request $request): SmartDidYouMean;

    abstract public function getLandingPageUri(): ?string;

    abstract public function getPromotionExtension(): ?Promotion;

    abstract public function getFiltersExtension(?Client $client = null): FiltersExtension;

    abstract public function getPaginationExtension(?int $limit, ?int $offset): Pagination;

    abstract public function getQueryInfoMessage(ShopwareEvent $event): QueryInfoMessage;
}
