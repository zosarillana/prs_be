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

    public function index()
    {
        return response()->json($this->service->getAll());
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
