<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Service\ItemPrice\ItemPriceService;
use App\Service\Paginator\PaginatorService;
use App\Helpers\QueryHelper;
use App\Helpers\MapItemPrice;

class ItemPriceController extends Controller
{
    // ✅ READ (TABLE)
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

        if (empty($queryParams['sortBy'])) {
            $queryParams['sortBy'] = 'id';
            $queryParams['sortOrder'] = 'desc';
        }

        $query = $itemPriceService
            ->getQuery($queryParams)
            ->reorder($queryParams['sortBy'], $queryParams['sortOrder']);

        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        $result['items'] = collect($result['items'])
            ->map(fn ($row) => MapItemPrice::mapTable($row))
            ->toArray();

        return response()->json($result);
    }

    // ✅ CREATE
    public function store(Request $request, ItemPriceService $service)
    {
        $data = $request->validate([
            'item_id'    => 'required|exists:items,id',
            'vendor_id'  => 'required|exists:vendors,id',
            'unit_price' => 'required|numeric|min:0',
        ]);

        $itemPrice = $service->create($data);

        return response()->json([
            'message' => 'Item price created successfully',
            'data' => MapItemPrice::mapTable($itemPrice),
        ], 201);
    }

    // ✅ READ (SINGLE)
    public function show($id, ItemPriceService $service)
    {
        $itemPrice = $service->findOrFail($id);

        return response()->json(
            MapItemPrice::mapTable($itemPrice)
        );
    }

    // ✅ UPDATE
    public function update(
        $id,
        Request $request,
        ItemPriceService $service
    ) {
        $data = $request->validate([
            'item_id'    => 'required|exists:items,id',
            'vendor_id'  => 'required|exists:vendors,id',
            'unit_price' => 'required|numeric|min:0',
        ]);

        $itemPrice = $service->update($id, $data);

        return response()->json([
            'message' => 'Item price updated successfully',
            'data' => MapItemPrice::mapTable($itemPrice),
        ]);
    }

    // ✅ DELETE
    public function destroy($id, ItemPriceService $service)
    {
        $service->delete($id);

        return response()->json([
            'message' => 'Item price deleted successfully'
        ]);
    }
}
