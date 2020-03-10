<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct\Filter\FilterValues;

use GuzzleHttp\Client;

use function GuzzleHttp\Promise\settle;

class FilterValueImageHandler
{
    /**
     * @var array
     */
    private $urls;

    /**
     * @var Client
     */
    private $client;

    public function __construct(array $urls)
    {
        $this->client = new Client();
        $this->urls = $urls;
    }

    /**
     * @return string[]
     */
    public function getValidImageUrls(): array
    {
        foreach ($this->urls as $name => $url) {
            $promises[$name] = $this->client->headAsync($url);
        }

        // Wait for the requests to complete, even if some of them fail
        $responses = settle($promises)->wait();

        // Only return responses that are valid and have image content in it
        return array_filter(
            $responses,
            static function ($value) {
                return $value['state'] === 'fulfilled' && isset($value['value']->getHeader('Content-Length')[0]);
            }
        );
    }
}
