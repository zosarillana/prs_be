<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vendor;

class VendorController extends Controller
{
    // ✅ READ (TABLE)
    public function index(Request $request)
    {
        $query = Vendor::query();

        if ($request->filled('searchTerm')) {
            $query->where('vendor_name', 'like', '%' . $request->searchTerm . '%');
        }

        $sortBy = $request->input('sortBy', 'id');
        $sortOrder = $request->input('sortOrder', 'desc');

        $vendors = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($request->input('pageSize', 10));

        return response()->json([
            'items' => $vendors->items(),
            'totalItems' => $vendors->total(),
            'totalPages' => $vendors->lastPage(),
            'currentPage' => $vendors->currentPage(),
        ]);
    }

    // ✅ CREATE
    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_name' => 'required|string|max:255',
        ]);

        $vendor = Vendor::create($data);

        return response()->json([
            'message' => 'Vendor created successfully',
            'data' => $vendor,
        ], 201);
    }

    // ✅ READ (SINGLE)
    public function show($id)
    {
        $vendor = Vendor::with(['itemPrices.item'])->findOrFail($id);

        return response()->json($vendor);
    }

    // ✅ UPDATE
    public function update(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $data = $request->validate([
            'vendor_name' => 'required|string|max:255',
        ]);

        $vendor->update($data);

        return response()->json([
            'message' => 'Vendor updated successfully',
            'data' => $vendor,
        ]);
    }

    // ✅ DELETE
    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->delete();

        return response()->json([
            'message' => 'Vendor deleted successfully',
        ]);
    }
}
