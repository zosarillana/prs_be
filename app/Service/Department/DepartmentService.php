<?php

namespace App\Service\Department;

use App\Events\Global\GlobalDepartmentCreated;
use App\Models\Department;
use Illuminate\Support\Collection;

class DepartmentService
{
    public function getAll(): Collection
    {
        return Department::all();
    }

    public function create(array $data): Department
    {
        $department = Department::create([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        // 🚀 Broadcast to everyone
        broadcast(new GlobalDepartmentCreated($department))->toOthers();

        return $department;
    }

    /**
     * Update a department (both name & description)
     */
    public function update(Department $department, array $data): Department
    {
        $department->update([
            'name' => $data['name'] ?? $department->name,
            'description' => $data['description'] ?? $department->description,
        ]);

        return $department;
    }

    public function delete(Department $department): bool
    {
        return $department->delete();
    }

    public function query()
    {
        return Department::query();
    }

    public function find($id)
    {
        return Department::find($id);
    }
}
