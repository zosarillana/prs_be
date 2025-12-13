<?php

namespace App\Service\UserPriviliges;

use App\Models\UserPrivileges;
use DB;
use Illuminate\Database\Eloquent\Collection;

class UserPriviligesService
{
    /**
     * Get all user privileges with optional filters
     *
     * @param array $filters Optional filters
     *   - user_id: int|array - Filter by user ID(s)
     *   - tag_ids: array - Filter by tag IDs (checks if ANY tag matches)
     *   - module_ids: array - Filter by module IDs (checks if ANY module matches)
     *   - with: array - Additional relationships to eager load
     *   - sort_by: string - Column to sort by (default: 'id')
     *   - sort_order: string - Sort order 'asc' or 'desc' (default: 'asc')
     *   - limit: int - Limit results
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        $query = UserPrivileges::with('user');

        // Filter by user_id (single or multiple)
        if (!empty($filters['user_id'])) {
            if (is_array($filters['user_id'])) {
                $query->whereIn('user_id', $filters['user_id']);
            } else {
                $query->where('user_id', $filters['user_id']);
            }
        }

        // Filter by tag_ids (checks if ANY tag exists in the array)
        if (!empty($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['tag_ids'] as $tagId) {
                    $q->orWhereJsonContains('tag_ids', $tagId);
                }
            });
        }

        // Filter by module_ids (checks if ANY module exists in the array)
        if (!empty($filters['module_ids']) && is_array($filters['module_ids'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['module_ids'] as $moduleId) {
                    $q->orWhereJsonContains('module_ids', $moduleId);
                }
            });
        }

        // Additional eager loading
        if (!empty($filters['with']) && is_array($filters['with'])) {
            $query->with($filters['with']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Limit
        if (!empty($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        return $query->get();
    }

    public function getById($id)
    {
        return UserPrivileges::with('user')->findOrFail($id);
    }

    public function create(array $data): UserPrivileges
    {
        return DB::transaction(function () use ($data) {
            return UserPrivileges::create([
                'user_id'    => $data['user_id'],
                'tag_ids'    => $data['tag_ids'] ?? [],
                'module_ids' => $data['module_ids'] ?? [],
            ]);
        });
    }

    public function update(UserPrivileges $privilege, array $data): UserPrivileges
    {
        return DB::transaction(function () use ($privilege, $data) {
            $privilege->update([
                'tag_ids'    => $data['tag_ids'] ?? $privilege->tag_ids,
                'module_ids' => $data['module_ids'] ?? $privilege->module_ids,
            ]);
            return $privilege;
        });
    }

    public function delete(UserPrivileges $privilege): bool
    {
        return $privilege->delete();
    }
}
