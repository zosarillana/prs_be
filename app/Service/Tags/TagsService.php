<?php

namespace App\Service\Tags;

use App\Models\Tags;
use Illuminate\Support\Collection;

class TagsService
{
    /**
     * Get all tags with optional filters
     *
     * @param array $filters Optional filters
     *   - department_id: int|array - Filter by department ID(s)
     *   - description: string - Search by description (partial match)
     *   - with: array - Additional relationships to eager load
     *   - sort_by: string - Column to sort by (default: 'id')
     *   - sort_order: string - Sort order 'asc' or 'desc' (default: 'asc')
     *   - limit: int - Limit results
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        $query = Tags::with('department');

        // Filter by department_id (single or multiple)
        if (!empty($filters['department_id'])) {
            if (is_array($filters['department_id'])) {
                $query->whereIn('department_id', $filters['department_id']);
            } else {
                $query->where('department_id', $filters['department_id']);
            }
        }

        // Search by description (partial match, case-insensitive)
        if (!empty($filters['description'])) {
            $query->where('description', 'like', '%' . $filters['description'] . '%');
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

    /**
     * Create a new tag.
     */
    public function create(array $data): Tags
    {
        return Tags::create([
            'department_id' => $data['department_id'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Update an existing tag.
     */
    public function update(Tags $tag, array $data): Tags
    {
        $tag->update([
            'department_id' => $data['department_id'] ?? $tag->department_id,
            'description' => $data['description'] ?? $tag->description,
        ]);

        return $tag;
    }

    /**
     * Delete a tag.
     */
    public function delete(Tags $tag): bool
    {
        return $tag->delete();
    }
}
