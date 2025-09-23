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
     * Display a listing of tags.
     */
    public function index()
    {
        return response()->json($this->tagService->getAll());
    }

    /**
     * Store a newly created tag.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'required|exists:department,id', // ✅ Check if department exists
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
