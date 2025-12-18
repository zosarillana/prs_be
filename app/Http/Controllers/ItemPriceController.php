<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Service\ItemPrice\ItemPriceService;
use App\Service\Paginator\PaginatorService;
use App\Helpers\QueryHelper;
use App\Helpers\MapItemPrice;

class ItemPriceController extends Controller
{
    public function table(
        Request $request,
        PaginatorService $paginator,
        ItemPriceService $itemPriceService
    ) {
        $result = QueryHelper::buildAndPaginate(
            $request,
            $itemPriceService,
            $paginator
        );

        $queryParams = array_merge(
            $result['query_params'] ?? [],
            $request->only([
                'searchTerm',
                'item',
                'vendor',
                'minPrice',
                'maxPrice',
                'sortBy',
                'sortOrder',
            ])
        );

        // Defaults
        if (empty($queryParams['sortBy'])) {
            $queryParams['sortBy'] = 'id';
            $queryParams['sortOrder'] = 'desc';
        }

        $query = $itemPriceService->getQuery($queryParams);

        // Sorting
        $query = $query->reorder(
            $queryParams['sortBy'],
            $queryParams['sortOrder']
        );

        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        // ✅ Mapping (same style as PurchaseReport)
        $result['items'] = collect($result['items'])
            ->map(fn ($row) => MapItemPrice::mapTable($row))
            ->toArray();

        return response()->json($result);
    }
}
