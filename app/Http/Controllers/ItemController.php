<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;

class ItemController extends Controller
{
    // ✅ READ (TABLE)
    public function index(Request $request)
    {
        $query = Item::query();

        if ($request->filled('searchTerm')) {
            $query->where('item_name', 'like', '%' . $request->searchTerm . '%');
        }

        $sortBy = $request->input('sortBy', 'id');
        $sortOrder = $request->input('sortOrder', 'desc');

        $items = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($request->input('pageSize', 10));

        return response()->json([
            'items' => $items->items(),
            'totalItems' => $items->total(),
            'totalPages' => $items->lastPage(),
            'currentPage' => $items->currentPage(),
        ]);
    }

    // ✅ CREATE
    public function store(Request $request)
    {
        $data = $request->validate([
            'item_name' => 'required|string|max:255',
        ]);

        $item = Item::create($data);

        return response()->json([
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    // ✅ READ (SINGLE)
    public function show($id)
    {
        $item = Item::with(['itemPrices.vendor'])->findOrFail($id);

        return response()->json($item);
    }

    // ✅ UPDATE
    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $data = $request->validate([
            'item_name' => 'required|string|max:255',
        ]);

        $item->update($data);

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
    }

    // ✅ DELETE
    public function destroy($id)
    {
        $item = Item::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }
}
