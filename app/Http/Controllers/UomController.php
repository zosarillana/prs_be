<?php

namespace App\Http\Controllers;

use App\Service\Uom\UomService;
use App\Service\Paginator\PaginatorService;
use App\Helpers\QueryHelper;
use Illuminate\Http\Request;

class UomController extends Controller
{
    protected $uomService;

    public function __construct(UomService $uomService)
    {
        $this->uomService = $uomService;
    }

    public function index(Request $request, PaginatorService $paginator)
{
    // Start a query on the UOM model (from your service)
    $query = $this->uomService->query();   // <-- add query() in service

    // 🔎 Optional search
    if ($search = $request->input('searchTerm')) {
        $query->where('description', 'like', "%{$search}%");
    }

    // ✅ Sorting
    $sortBy    = $request->input('sortBy', 'id');
    $sortOrder = $request->input('sortOrder', 'desc');
    $query->orderBy($sortBy, $sortOrder);

    // ✅ Pagination
    $result = $paginator->paginate(
        $query,
        $request->input('pageNumber', 1),
        $request->input('pageSize', 10)
    );

    return response()->json($result);
}

    public function store(Request $request)
    {
        $data = $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $uom = $this->uomService->create($data);

        return response()->json($uom, 201);
    }

    public function show($id)
    {
        $uom = $this->uomService->find($id);

        if (! $uom) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($uom);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $uom = $this->uomService->update($id, $data);

        if (! $uom) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($uom);
    }

    public function destroy($id)
    {
        $deleted = $this->uomService->delete($id);

        if (! $deleted) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['message' => 'Deleted successfully']);
    }
}
