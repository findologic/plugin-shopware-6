<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Client;

use GuzzleHttp\Client;

class FindologicClientFactory
{
    public function createServiceConfigClient(string $shopkey, ?Client $client = null)
    {
        return new ServiceConfigClient($shopkey, $client);
    }
}
