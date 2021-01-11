<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Api;

use FINDOLOGIC\FinSearch\Struct\Pagination;
use Symfony\Component\HttpFoundation\Request;

class PaginationService
{
    public function getRequestOffset(Request $request, ?int $limit): int
    {
        $limit = $limit ?? Pagination::DEFAULT_LIMIT;
        $page = $this->getCurrentPage($request);

        return $this->calculateOffset($page, $limit);
    }

    protected function getCurrentPage(Request $request): int
    {
        $page = $request->query->getInt('p', 1);
        if ($request->isMethod(Request::METHOD_POST)) {
            $page = $request->request->getInt('p', $page);
        }

        if ($page <= 0) {
            return 1;
        }

        return $page;
    }

    protected function calculateOffset(int $page, int $limit): int
    {
        return ($page - 1) * $limit;
    }
}
