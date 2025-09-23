<?php

namespace App\Service\Modules;

use App\Models\Modules;
use DB;

class ModulesService
{
    /**
     * Get all modules
     */
    public function getAll()
    {
        return Modules::all();
    }

    /**
     * Get a single module by ID
     */
    public function getById($id): Modules
    {
        return Modules::findOrFail($id);
    }

    /**
     * Create a new module
     */
    public function create(array $data): Modules
    {
        return DB::transaction(function () use ($data) {
            return Modules::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Update an existing module
     */
    public function update(Modules $module, array $data): Modules
    {
        return DB::transaction(function () use ($module, $data) {
            $module->update([
                'name' => $data['name'] ?? $module->name,
                'description' => $data['description'] ?? $module->description,
            ]);
            return $module;
        });
    }

    /**
     * Delete a module
     */
    public function delete(Modules $module): bool
    {
        return $module->delete();
    }
}