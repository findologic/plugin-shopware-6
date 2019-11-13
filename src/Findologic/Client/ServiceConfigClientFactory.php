<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Client;

use GuzzleHttp\Client;

class ServiceConfigClientFactory
{
    public function getInstance(string $shopkey, ?Client $client = null): ServiceConfigClient
    {
        return new ServiceConfigClient($shopkey, $client);
    }
}
