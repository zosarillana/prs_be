<?php

namespace App\Service\Tags;

use App\Models\Tags;
use Illuminate\Support\Collection;

class TagsService
{
    /**
     * Get all tags with their department relationship.
     */
    public function getAll(): Collection
    {
        return Tags::with('department')->get();
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
