<?php

namespace App\Http\Controllers;

use App\Models\Modules;
use App\Service\Modules\ModulesService;
use Illuminate\Http\Request;

class ModulesController extends Controller
{
    protected $service;

    public function __construct(ModulesService $service)
    {
        $this->service = $service;
    }

    /**
     * List all modules
     */
    public function index()
    {
        return response()->json($this->service->getAll());
    }

    /**
     * Store a new module
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $module = $this->service->create($validated);
        return response()->json($module, 201);
    }

    /**
     * Show a single module
     */
    public function show($id)
    {
        return response()->json($this->service->getById($id));
    }

    /**
     * Update an existing module
     */
    public function update(Request $request, Modules $module)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $updated = $this->service->update($module, $validated);
        return response()->json($updated);
    }

    /**
     * Delete a module
     */
    public function destroy(Modules $module)
    {
        $this->service->delete($module);
        return response()->json(['message' => 'Deleted successfully']);
    }
}
