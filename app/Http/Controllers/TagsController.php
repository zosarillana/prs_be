<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tags;
use App\Service\Tags\TagsService;
use Illuminate\Http\Request;

class TagsController extends Controller
{
    protected TagsService $tagService;

    public function __construct(TagsService $tagService)
    {
        $this->tagService = $tagService;
    }

    /**
     * Display a listing of tags with optional filters.
     *
     * Query parameters:
     * - department_id: int|string (comma-separated for multiple)
     * - description: string (search term)
     * - sort_by: string
     * - sort_order: asc|desc
     * - limit: int
     */
    public function index(Request $request)
    {
        $filters = [];

        // Parse department_id (single or comma-separated)
        if ($request->has('department_id')) {
            $deptIds = $request->input('department_id');
            $filters['department_id'] = str_contains($deptIds, ',')
                ? array_map('intval', explode(',', $deptIds))
                : (int) $deptIds;
        }

        // Search by description
        if ($request->has('description')) {
            $filters['description'] = $request->input('description');
        }

        // Sorting
        if ($request->has('sort_by')) {
            $filters['sort_by'] = $request->input('sort_by');
        }

        if ($request->has('sort_order')) {
            $filters['sort_order'] = $request->input('sort_order');
        }

        // Limit
        if ($request->has('limit')) {
            $filters['limit'] = (int) $request->input('limit');
        }

        return response()->json($this->tagService->getAll($filters));
    }

    /**
     * Store a newly created tag.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'required|exists:department,id',
            'description'   => 'nullable|string|max:255',
        ]);

        $tag = $this->tagService->create($data);

        return response()->json($tag, 201);
    }

    /**
     * Display a specific tag.
     */
    public function show(Tags $tag)
    {
        return response()->json($tag->load('department'));
    }

    /**
     * Update the specified tag.
     */
    public function update(Request $request, Tags $tag)
    {
        $data = $request->validate([
            'department_id' => 'sometimes|exists:department,id',
            'description'   => 'nullable|string|max:255',
        ]);

        $tag = $this->tagService->update($tag, $data);

        return response()->json($tag);
    }

    /**
     * Remove the specified tag.
     */
    public function destroy(Tags $tag)
    {
        $this->tagService->delete($tag);

        return response()->json(['message' => 'Tag deleted successfully']);
    }
}
