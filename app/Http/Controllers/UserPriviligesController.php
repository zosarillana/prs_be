<?php

namespace App\Http\Controllers;

use App\Models\UserPrivileges;
use App\Service\UserPriviliges\UserPriviligesService;
use Illuminate\Http\Request;

class UserPriviligesController extends Controller
{
    protected $service;

    public function __construct(UserPriviligesService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = [];

        // Parse user_id (single or comma-separated)
        if ($request->has('user_id')) {
            $userIds = $request->input('user_id');
            $filters['user_id'] = str_contains($userIds, ',')
                ? array_map('intval', explode(',', $userIds))
                : (int) $userIds;
        }

        // Parse tag_ids (comma-separated)
        if ($request->has('tag_ids')) {
            $filters['tag_ids'] = array_map('intval', explode(',', $request->input('tag_ids')));
        }

        // Parse module_ids (comma-separated)
        if ($request->has('module_ids')) {
            $filters['module_ids'] = array_map('intval', explode(',', $request->input('module_ids')));
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

        return response()->json($this->service->getAll($filters));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'tag_ids'    => 'nullable|array',
            'module_ids' => 'nullable|array',
        ]);

        $privilege = $this->service->create($validated);
        return response()->json($privilege, 201);
    }

    public function show($id)
    {
        return response()->json($this->service->getById($id));
    }

    public function update(Request $request, UserPrivileges $userPrivilege)
    {
        $validated = $request->validate([
            'tag_ids'    => 'nullable|array',
            'module_ids' => 'nullable|array',
        ]);

        $updated = $this->service->update($userPrivilege, $validated);
        return response()->json($updated);
    }

    public function destroy(UserPrivileges $userPrivilege)
    {
        $this->service->delete($userPrivilege);
        return response()->json(['message' => 'Deleted successfully']);
    }
}
