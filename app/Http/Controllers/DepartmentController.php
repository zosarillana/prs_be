<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Service\Department\DepartmentService;
use App\Service\Paginator\PaginatorService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    protected DepartmentService $departmentService;

    public function __construct(DepartmentService $departmentService)
    {
        $this->departmentService = $departmentService;
    }

    public function index(Request $request, PaginatorService $paginator)
    {
        $query = $this->departmentService->query();

        if ($search = $request->input('searchTerm')) {
            $query->where('description', 'like', "%{$search}%");
        }

        $sortBy    = $request->input('sortBy', 'id');
        $sortOrder = $request->input('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

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
            'description' => 'nullable|string|max:255',
            'name'        => 'nullable|string|max:255',
        ]);

        $department = $this->departmentService->create($data);

        return response()->json($department, 201);
    }

    public function show($id)
    {
        $department = $this->departmentService->find($id);

        if (! $department) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($department);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'description' => 'nullable|string|max:255',
               'name'        => 'nullable|string|max:255',
        ]);

        // ✅ Find model first
        $department = $this->departmentService->find($id);

        if (! $department) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // ✅ Pass model instance to service
        $updated = $this->departmentService->update($department, $data);

        return response()->json($updated);
    }

    public function destroy($id)
    {
        $department = $this->departmentService->find($id);

        if (! $department) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $this->departmentService->delete($department);

        return response()->json(['message' => 'Deleted successfully']);
    }
}
