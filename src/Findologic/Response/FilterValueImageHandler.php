<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Response;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Sends requests to a customer's directory, to check whether images are available or not.
 * This may only be relevant for vendor image and color filters.
 */
class FilterValueImageHandler
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    /**
     * Sends asynchronous HEAD requests to fetch images. Returns an array of actual found images.
     *
     * @param string[] $urls
     *
     * @return string[]
     */
    public function getValidImageUrls(array $urls): array
    {
        $requests = $this->getRequests($urls);

        $validUrls = [];
        $pool = new Pool($this->client, $requests, [
            'concurrency' => 8,
            'fulfilled' => function (Response $response, $index) use (&$validUrls, &$requests): void {
                if ($response->getStatusCode() === 200) {
                    /** @var Request $request */
                    $request = $requests[$index];

                    $validUrls[] = $request->getUri()->__toString();
                }
            }
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $validUrls;
    }

    private function getRequests(array $urls): array
    {
        $requests = [];
        foreach ($urls as $name => $url) {
            $requests[] = new Request('HEAD', $url);
        }

        return $requests;
    }
}
