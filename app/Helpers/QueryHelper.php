<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use App\Service\PurchaseReport\PurchaseReportService;
use App\Service\Paginator\PaginatorService;

class QueryHelper
{
    /**
     * Build & paginate a purchase report query.
     */
    public static function buildAndPaginate(
        Request $request,
        PurchaseReportService $service,
        PaginatorService $paginator,
        array $defaults = []
    ): array {
        // Merge request inputs with defaults
        $params = [
            'searchTerm' => $request->input('searchTerm', $defaults['searchTerm'] ?? null),
            'fromDate'   => $request->input('fromDate',   $defaults['fromDate']   ?? null),
            'toDate'     => $request->input('toDate',     $defaults['toDate']     ?? null),
            'sortBy'     => $request->input('sortBy',     $defaults['sortBy']     ?? 'id'),
            'sortOrder'  => $request->input('sortOrder',  $defaults['sortOrder']  ?? 'asc'),
        ];

        $query = $service->getQuery($params);

        // ✅ Return pagination result AND the params for later reuse
        return array_merge(
            $paginator->paginate(
                $query,
                $request->input('pageNumber', $defaults['pageNumber'] ?? 1),
                $request->input('pageSize',   $defaults['pageSize']   ?? 10)
            ),
            [
                'query_params' => $params, // <-- ADDED
            ]
        );
    }
}
