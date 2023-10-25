<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Client;

use FINDOLOGIC\FinSearch\Findologic\BaseUrl;
use FINDOLOGIC\FinSearch\Findologic\RequestUri;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ServiceConfigClient
{
    public function __construct(
        private readonly string $shopkey,
        private ?Client $client = null
    ) {
        if (!isset($this->client->getConfig()['base_uri']) || $this->client->getConfig()['base_uri'] != BaseUrl::CDN) {
            $this->client = new Client(['base_uri' => BaseUrl::CDN]);
        }
    }

    /**
     * @throws ClientException
     */
    public function get(): array
    {
        $uri = sprintf(RequestUri::SERVICE_CONFIG_RESOURCE, $this->shopkey);
        $response = $this->client->get($uri);
        $content = $response->getBody()->getContents();

        return json_decode($content, true);
    }
}
