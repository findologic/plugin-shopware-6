<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Responses;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PreconditionFailedResponse extends JsonResponse
{
    public function __construct()
    {
        $errorMessage = 'Dynamic Product Groups have not been warmed up yet. ';
        $errorMessage .= 'This may cause missing categories! ';
        $errorMessage .= "Warm them up by calling the route '/findologic/dynamic-product-groups', ";
        $errorMessage .= 'or disable fetching of Dynamic Product Groups by adding the query parameter ';
        $errorMessage .= "'excludeProductGroups=true'";

        parent::__construct(['error' => $errorMessage], Response::HTTP_PRECONDITION_REQUIRED);
    }
}
