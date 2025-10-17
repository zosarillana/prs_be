<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PrRoleFilters
{
    public static function applyRoleFilters(Builder $query, User $user): Builder
    {
        $departments = collect($user->department ?? [])->map(function ($dept) {
            return [
                'original' => $dept,
                'slug' => preg_replace('/[^A-Za-z0-9_.-]/', '_', $dept),
            ];
        });

        $roles = $user->role ?? [];

        // ✅ Admins and purchasing see everything (including cancelled)
        if (in_array('admin', $roles) || in_array('purchasing', $roles)) {
            return $query;
        }

        // ✅ Technical Reviewer — filter by tag.department JSON field
        if (in_array('technical_reviewer', $roles)) {
            $query->where(function ($q) use ($departments) {
                foreach ($departments as $dept) {
                    $q->orWhereRaw(
                        "JSON_CONTAINS(JSON_EXTRACT(tag, '$[*].department'), JSON_QUOTE(?))",
                        [$dept['original']]
                    )->orWhereRaw(
                        "JSON_CONTAINS(JSON_EXTRACT(tag, '$[*].department'), JSON_QUOTE(?))",
                        [$dept['slug']]
                    );
                }
            });

            // ✅ Can also see cancelled, but only those matching their department tags
            return $query;
        }

        // ✅ HOD and regular users — only see their own department (including cancelled)
        $query->where(function ($outer) use ($departments) {
            if ($departments->isNotEmpty()) {
                $deptNames = $departments->flatMap(fn($d) => [$d['original'], $d['slug']])->all();
                $outer->whereIn('department', $deptNames);
            }
        });

        return $query;
    }
}
