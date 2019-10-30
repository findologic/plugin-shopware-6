<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Client;

use FINDOLOGIC\FinSearch\Findologic\BaseUrl;
use FINDOLOGIC\FinSearch\Findologic\RequestUri;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ServiceConfigClient
{
    /** @var string */
    private $shopkey;

    /** @var Client */
    private $client;

    public function __construct(string $shopkey, ?Client $client = null)
    {
        $this->shopkey = $shopkey;
        $this->client = $client ?? new Client(['base_uri' => BaseUrl::CDN]);
    }

    /**
     * @throws ClientException
     */
    public function get(): array
    {
        $uri = sprintf(RequestUri::SERVICE_CONFIG_RESOURCE, strtoupper(md5($this->shopkey)));
        $response = $this->client->get($uri);
        $content = $response->getBody()->getContents();

        return json_decode($content, true);
    }
}
